package com.example.deiv.cases

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.ProgressBar
import android.widget.TextView
import androidx.fragment.app.Fragment
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.example.deiv.login.SessionManager
import com.google.android.material.floatingactionbutton.FloatingActionButton
import org.json.JSONObject
import androidx.navigation.fragment.findNavController
import android.graphics.Color
import android.util.Log
import java.text.SimpleDateFormat
import java.util.*
import android.widget.Toast

class CaseFragment : Fragment() {

    private lateinit var recyclerCases: RecyclerView
    private lateinit var fabAddCase: FloatingActionButton
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var progressBar: ProgressBar
    private lateinit var tvNoCases: TextView

    private val caseAdapter = CaseAdapter { caseId ->
        val bundle = Bundle()
        bundle.putInt("case_id", caseId)

        findNavController().navigate(
            R.id.caseDetailFragment,
            bundle
        )
    }

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        val view = inflater.inflate(R.layout.fragment_case, container, false)

        recyclerCases = view.findViewById(R.id.recyclerCases)
        fabAddCase = view.findViewById(R.id.fabAddCase)
        swipeRefresh = view.findViewById(R.id.swipeRefresh)
        progressBar = view.findViewById(R.id.progressBar)
        tvNoCases = view.findViewById(R.id.tvNoCases)

        // Setup RecyclerView
        recyclerCases.layoutManager = LinearLayoutManager(context)
        recyclerCases.adapter = caseAdapter

        // Setup FAB click
        fabAddCase.setOnClickListener {
            showAddCaseDialog()
        }

        // Setup swipe to refresh
        swipeRefresh.setOnRefreshListener {
            loadCases()
        }

        // Load cases initially
        loadCases()

        return view
    }

    private fun loadCases() {
        progressBar.visibility = View.VISIBLE
        tvNoCases.visibility = View.GONE

        val session = SessionManager(requireContext())
        val userId = session.getUserId()

        if (userId <= 0) {
            progressBar.visibility = View.GONE
            swipeRefresh.isRefreshing = false
            return
        }

        // Use centralized API URL
        val url = "${ApiService.CASE_LIST}?user_id=$userId"

        val queue = Volley.newRequestQueue(requireContext())
        val stringRequest = StringRequest(
            Request.Method.GET,
            url,
            { response ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false

                try {
                    val json = JSONObject(response)

                    if (json.getString("status") == "success") {
                        if (json.has("cases") && !json.isNull("cases")) {
                            val casesArray = json.getJSONArray("cases")

                            if (casesArray.length() > 0) {
                                tvNoCases.visibility = View.GONE
                                recyclerCases.visibility = View.VISIBLE
                                caseAdapter.setData(casesArray)
                            } else {
                                tvNoCases.visibility = View.VISIBLE
                                recyclerCases.visibility = View.GONE
                            }
                        } else {
                            tvNoCases.visibility = View.VISIBLE
                            recyclerCases.visibility = View.GONE
                        }
                    } else {
                        tvNoCases.visibility = View.VISIBLE
                        recyclerCases.visibility = View.GONE
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    tvNoCases.visibility = View.VISIBLE
                    recyclerCases.visibility = View.GONE
                }
            },
            { error ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false
                tvNoCases.visibility = View.VISIBLE
                recyclerCases.visibility = View.GONE
                error.printStackTrace()
            }
        )

        queue.add(stringRequest)
    }

    @SuppressLint("UseKtx")
    private fun showAddCaseDialog() {
        val dialogView = LayoutInflater.from(requireContext())
            .inflate(R.layout.dialog_add_case, null)

        val etCaseName = dialogView.findViewById<EditText>(R.id.etCaseName)
        val etDescription = dialogView.findViewById<EditText>(R.id.etDescription)

        val alertDialog = AlertDialog.Builder(requireContext())
            .setView(dialogView)
            .setPositiveButton("Create") { dialog, _ ->
                val caseName = etCaseName.text.toString().trim()
                val description = etDescription.text.toString().trim()

                if (caseName.isNotEmpty() && description.isNotEmpty()) {
                    createNewCase(caseName, description)
                }
                dialog.dismiss()
            }
            .setNegativeButton("Cancel") { dialog, _ ->
                dialog.dismiss()
            }
            .create()

        alertDialog.show()

        // Set background color to white (#FFFFFF)
        alertDialog.window?.setBackgroundDrawableResource(android.R.color.white)

        // Set button colors to light dark purple
        val darkPurple = Color.parseColor("#6A5ACD") // A light dark purple (SlateBlue)
        setDialogButtonTextColor(alertDialog, darkPurple)
    }

    private fun setDialogButtonTextColor(dialog: AlertDialog, color: Int) {
        // Get the buttons
        val positiveButton = dialog.getButton(AlertDialog.BUTTON_POSITIVE)
        val negativeButton = dialog.getButton(AlertDialog.BUTTON_NEGATIVE)

        // Set text color for both buttons
        positiveButton?.setTextColor(color)
        negativeButton?.setTextColor(color)
    }

    private fun createNewCase(caseName: String, description: String) {
        val session = SessionManager(requireContext())
        val userId = session.getUserId()

        if (userId <= 0) {
            Toast.makeText(requireContext(), "User not authenticated", Toast.LENGTH_SHORT).show()
            return
        }

        // Show progress (you might want to add a progress dialog here)
        val queue = Volley.newRequestQueue(requireContext())
        val stringRequest = object : StringRequest(
            Method.POST,
            ApiService.CASE_REGISTER,
            { response ->
                try {
                    val json = JSONObject(response)
                    if (json.getString("status") == "success") {
                        Toast.makeText(
                            requireContext(),
                            "Case created successfully!",
                            Toast.LENGTH_SHORT
                        ).show()

                        // Reload cases after successful creation
                        loadCases()
                    } else {
                        Toast.makeText(
                            requireContext(),
                            "Failed: ${json.getString("message")}",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    Toast.makeText(
                        requireContext(),
                        "Error parsing response",
                        Toast.LENGTH_SHORT
                    ).show()
                }
            },
            { error ->
                error.printStackTrace()
                // Add more detailed error information
                val errorMsg = error.message ?: "Unknown error"
                val networkResponse = error.networkResponse
                val statusCode = networkResponse?.statusCode ?: 0
                val responseData = if (networkResponse?.data != null) {
                    String(networkResponse.data, Charsets.UTF_8)
                } else {
                    "No response data"
                }

                Log.e("CreateCase", "Error: $errorMsg, Status: $statusCode, Response: $responseData")

                Toast.makeText(
                    requireContext(),
                    "Creation failed: $errorMsg",
                    Toast.LENGTH_LONG
                ).show()
            }
        ) {
            override fun getParams(): Map<String, String> {
                val params = HashMap<String, String>()
                params["case_name"] = caseName
                params["description"] = description
                params["status"] = "Pending"
                params["user_id"] = userId.toString()  // Important: Include user_id
                return params
            }

            override fun getBodyContentType(): String {
                return "application/x-www-form-urlencoded"
            }

            // Optional: Add headers if needed
            override fun getHeaders(): Map<String, String> {
                val headers = HashMap<String, String>()
                headers["Content-Type"] = "application/x-www-form-urlencoded"
                headers["Accept"] = "application/json"
                return headers
            }
        }

        queue.add(stringRequest)
    }
}