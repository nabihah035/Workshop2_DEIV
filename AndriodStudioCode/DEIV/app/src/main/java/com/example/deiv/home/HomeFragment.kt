package com.example.deiv.home

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
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

class HomeFragment : Fragment() {

    private lateinit var tvTotalCases: TextView
    private lateinit var tvEvidenceUploads: TextView
    private lateinit var recyclerRecentCases: RecyclerView
    private lateinit var sessionManager: SessionManager

    private val recentCaseAdapter = RecentCaseAdapter()

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {

        val view = inflater.inflate(R.layout.fragment_home, container, false)

        // Initialize SessionManager
        sessionManager = SessionManager(requireContext())

        // Views
        tvTotalCases = view.findViewById(R.id.tvTotalCases)
        tvEvidenceUploads = view.findViewById(R.id.tvEvidenceUploads)
        recyclerRecentCases = view.findViewById(R.id.recyclerRecentCases)
        val tvViewAll = view.findViewById<TextView>(R.id.tvViewAll)

        // ✅ View All click → navigate to CaseFragment
        tvViewAll.setOnClickListener {
            findNavController().navigate(R.id.nav_case)
        }

        // RecyclerView setup
        recyclerRecentCases.layoutManager = LinearLayoutManager(requireContext())
        recyclerRecentCases.adapter = recentCaseAdapter

        return view
    }

    override fun onResume() {
        super.onResume()
        // Load dashboard data when fragment is visible
        loadDashboard()
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
}