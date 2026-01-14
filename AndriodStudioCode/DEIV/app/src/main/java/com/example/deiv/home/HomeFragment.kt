package com.example.deiv.home

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageButton
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.example.deiv.login.LoginActivity
import com.example.deiv.login.SessionManager
import org.json.JSONObject
import android.content.Intent
import com.example.deiv.MainActivity
import com.google.android.material.bottomnavigation.BottomNavigationView
import com.android.volley.toolbox.JsonObjectRequest


class HomeFragment : Fragment() {

    private lateinit var tvWelcomeUser: TextView
    private lateinit var tvTotalCases: TextView
    private lateinit var tvEvidenceUploads: TextView
    private lateinit var recyclerRecentCases: RecyclerView
    private lateinit var sessionManager: SessionManager
    private lateinit var btnNotificationHeader: ImageButton
    private lateinit var btnUserHeader: ImageButton
    private lateinit var tvNotificationBadge: TextView

    private val recentCaseAdapter = RecentCaseAdapter()

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        val view = inflater.inflate(R.layout.fragment_home, container, false)

        // Initialize SessionManager
        sessionManager = SessionManager(requireContext())

        tvNotificationBadge = view.findViewById(R.id.tv_notification_badge)

        // Initialize Views
        tvWelcomeUser = view.findViewById(R.id.tvWelcomeUser)
        tvTotalCases = view.findViewById(R.id.tvTotalCases)
        tvEvidenceUploads = view.findViewById(R.id.tvEvidenceUploads)
        recyclerRecentCases = view.findViewById(R.id.recyclerRecentCases)
        btnNotificationHeader = view.findViewById(R.id.btn_notification_header)
        btnUserHeader = view.findViewById(R.id.btn_user_header)
        val tvViewAll = view.findViewById<TextView>(R.id.tvViewAll)

        // Set welcome message
        val userName = sessionManager.getName()
        tvWelcomeUser.text = if (userName.isNotEmpty()) userName else sessionManager.getUsername()

        tvViewAll.setOnClickListener {
            // Use the bottom navigation to navigate
            val mainActivity = activity as? MainActivity
            mainActivity?.findViewById<BottomNavigationView>(R.id.bottomNav)?.selectedItemId = R.id.nav_case
        }

        // Header buttons - FIXED NAVIGATION
        btnNotificationHeader.setOnClickListener {
            // Navigate to notification activity directly
            try {
                val intent = Intent(requireContext(), com.example.deiv.notification.NotificationActivity::class.java)
                startActivity(intent)
            } catch (e: Exception) {
                Log.e("HomeFragment", "Error starting NotificationActivity: ${e.message}")
                Toast.makeText(requireContext(), "Could not open notifications", Toast.LENGTH_SHORT).show()
            }
        }

        btnUserHeader.setOnClickListener {
            // Use the bottom navigation to navigate
            val mainActivity = activity as? MainActivity
            mainActivity?.findViewById<BottomNavigationView>(R.id.bottomNav)?.selectedItemId = R.id.nav_user
        }

        // RecyclerView setup
        recyclerRecentCases.layoutManager = LinearLayoutManager(requireContext())
        // Use the updated RecentCaseAdapter
        recyclerRecentCases.adapter = recentCaseAdapter

        return view
    }

    override fun onResume() {
        super.onResume()
        // Load dashboard data when fragment is visible
        loadDashboard()
        // Check for unread notifications
        fetchUnreadNotificationsCount()
    }

    private fun loadDashboard() {
        val userId = sessionManager.getUserId()

        Log.d("HomeFragment", "Loading dashboard for user ID: $userId")

        if (userId <= 0) {
            Log.e("HomeFragment", "Invalid user ID: $userId. Redirecting to login...")
            Toast.makeText(requireContext(), "Session expired. Please login again.", Toast.LENGTH_SHORT).show()
            redirectToLogin()
            return
        }

        // Use centralized API URL with proper encoding
        val url = "${ApiService.HOME_DATA}?user_id=$userId"
        Log.d("HomeFragment", "Request URL: $url")

        val queue = Volley.newRequestQueue(requireContext())

        val stringRequest = StringRequest(
            Request.Method.GET,
            url,
            { response ->
                try {
                    Log.d("HomeFragment", "Response: $response")
                    val json = JSONObject(response)

                    when (json.getString("status")) {
                        "success" -> {
                            tvTotalCases.text = json.getInt("total_cases").toString()
                            tvEvidenceUploads.text = json.getInt("total_evidence").toString()

                            if (json.has("recent_cases")) {
                                recentCaseAdapter.setData(
                                    json.getJSONArray("recent_cases")
                                )
                            }
                        }
                        "error" -> {
                            val message = json.optString("message", "Unknown error")
                            Toast.makeText(requireContext(), "Error: $message", Toast.LENGTH_SHORT).show()
                            Log.e("HomeFragment", "API Error: $message")

                            // Check if it's a session issue
                            if (message.contains("User not found") || message.contains("Invalid user")) {
                                redirectToLogin()
                            }
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    Log.e("HomeFragment", "JSON parsing error: ${e.message}")
                    Toast.makeText(requireContext(), "Failed to load data", Toast.LENGTH_SHORT).show()
                }
            },
            { error ->
                error.printStackTrace()
                Log.e("HomeFragment", "Volley error: ${error.message}")
                Toast.makeText(requireContext(), "Network error: ${error.message}", Toast.LENGTH_SHORT).show()

                // Check network status
                if (error.networkResponse == null) {
                    Toast.makeText(requireContext(), "No internet connection", Toast.LENGTH_SHORT).show()
                }
            }
        )

        queue.add(stringRequest)
    }

    private fun redirectToLogin() {
        sessionManager.logout()
        val intent = Intent(requireContext(), LoginActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        startActivity(intent)
        requireActivity().finish()
    }

    private fun updateNotificationBadge(count: Int) {
        if (count > 0) {
            tvNotificationBadge.text = count.toString()
            tvNotificationBadge.visibility = View.VISIBLE
        } else {
            tvNotificationBadge.visibility = View.GONE
        }
    }


    private fun fetchUnreadNotificationsCount() {
        val userId = sessionManager.getUserId()
        if (userId <= 0) return

        val url = "${ApiService.NOTIFICATION_LIST}?user_id=$userId&type=unread"

        val request = JsonObjectRequest(
            Request.Method.GET, url, null,
            { response ->
                try {
                    if (response.getBoolean("success")) {
                        val array = response.getJSONArray("notifications")
                        val unreadCount = array.length()
                        updateNotificationBadge(unreadCount)
                    }
                } catch (e: Exception) {
                    Log.e("HomeFragment", "Error parsing unread count: ${e.message}")
                }
            },
            { error ->
                Log.e("HomeFragment", "Error fetching unread count: ${error.message}")
            }
        )

        Volley.newRequestQueue(requireContext()).add(request)
    }

}