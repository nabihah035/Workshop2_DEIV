package com.example.deiv.home

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.example.deiv.login.SessionManager
import org.json.JSONObject

class HomeFragment : Fragment() {

    private lateinit var tvTotalCases: TextView
    private lateinit var tvEvidenceUploads: TextView
    private lateinit var recyclerRecentCases: RecyclerView

    private val recentCaseAdapter = RecentCaseAdapter()

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {

        val view = inflater.inflate(R.layout.fragment_home, container, false)

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

        // Load dashboard data
        loadDashboard()

        return view
    }

    private fun loadDashboard() {
        val session = SessionManager(requireContext())
        val userId = session.getUserId()

        if (userId <= 0) {
            println("DEBUG: Invalid user ID: $userId")
            return
        }

        // Use centralized API URL
        val url = "${ApiService.HOME_DATA}?user_id=$userId"
        val queue = Volley.newRequestQueue(requireContext())

        val stringRequest = StringRequest(
            Request.Method.GET,
            url,
            { response ->
                try {
                    val json = JSONObject(response)

                    if (json.getString("status") == "success") {
                        tvTotalCases.text = json.getInt("total_cases").toString()
                        tvEvidenceUploads.text = json.getInt("total_evidence").toString()

                        if (json.has("recent_cases")) {
                            recentCaseAdapter.setData(
                                json.getJSONArray("recent_cases")
                            )
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            },
            { error ->
                error.printStackTrace()
            }
        )

        queue.add(stringRequest)
    }
}
