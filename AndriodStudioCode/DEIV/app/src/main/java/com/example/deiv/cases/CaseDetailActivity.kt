@file:Suppress("DEPRECATION")

package com.example.deiv.cases

import android.annotation.SuppressLint
import android.os.Bundle
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.R
import com.example.deiv.api.ApiService
import org.json.JSONObject
import java.util.*
import android.view.View
import android.content.Intent
import android.app.ProgressDialog
import android.app.AlertDialog
import android.util.Log
import com.example.deiv.login.SessionManager

class CaseDetailActivity : AppCompatActivity() {

    private lateinit var recyclerEvidence: RecyclerView
    private lateinit var progressBar: ProgressBar
    private lateinit var tvCaseName: TextView
    private lateinit var tvCaseDescription: TextView
    private lateinit var tvCaseStatus: TextView
    private lateinit var tvTotalEvidence: TextView
    private lateinit var tvAssignedTo: TextView
    private lateinit var tvCaseNumber: TextView
    private lateinit var tvEvidenceHeader: TextView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var btnBack: ImageView
    private lateinit var tvNoEvidence: TextView
    private lateinit var btnAddEvidence: Button
    private lateinit var evidenceAdapter: EvidenceAdapter
    private lateinit var spinnerStatus: Spinner

    private var caseId: Int = -1
    private var currentCaseStatus: String = "" // Track current status

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_case_detail)

        // Get case ID from intent
        caseId = intent.getIntExtra("case_id", -1)

        if (caseId <= 0) {
            Toast.makeText(this, "Invalid Case ID", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        evidenceAdapter = EvidenceAdapter()

        initializeViews()
        setupListeners()
        loadCaseDetails(caseId)
    }

    private fun initializeViews() {
        btnBack = findViewById(R.id.btnBack)
        recyclerEvidence = findViewById(R.id.recyclerEvidence)
        progressBar = findViewById(R.id.progressBar)
        tvCaseName = findViewById(R.id.tvCaseName)
        tvCaseDescription = findViewById(R.id.tvCaseDescription)
        tvCaseStatus = findViewById(R.id.tvCaseStatus)
        tvTotalEvidence = findViewById(R.id.tvTotalEvidence)
        tvAssignedTo = findViewById(R.id.tvAssignedTo)
        tvCaseNumber = findViewById(R.id.tvCaseNumber)
        tvEvidenceHeader = findViewById(R.id.tvEvidenceHeader)
        swipeRefresh = findViewById(R.id.swipeRefresh)
        tvNoEvidence = findViewById(R.id.tvNoEvidence)
        btnAddEvidence = findViewById(R.id.btnAddEvidence)
        spinnerStatus = findViewById(R.id.spinnerStatus)

        recyclerEvidence.layoutManager = LinearLayoutManager(this)
        recyclerEvidence.adapter = evidenceAdapter
    }

    private fun setupListeners() {
        btnBack.setOnClickListener {
            finish()
        }

        btnAddEvidence.setOnClickListener {
            val intent = Intent(this, AddEvidenceActivity::class.java)
            intent.putExtra("case_id", caseId)
            // Pass the case name from the TextView
            intent.putExtra("case_name", tvCaseName.text.toString())
            startActivity(intent)
        }

        swipeRefresh.setOnRefreshListener {
            loadCaseDetails(caseId)
        }
    }

    private fun setupStatusSpinner(status: String) {
        // Update current status
        currentCaseStatus = status

        // Status options from your database
        val statusOptions = listOf("In Progress", "Complete", "Closed", "Pending")

        // Create adapter
        val adapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_item,
            statusOptions
        )
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)

        spinnerStatus.adapter = adapter

        // Set current status as selected
        val currentIndex = statusOptions.indexOfFirst { it.equals(status, ignoreCase = true) }
        if (currentIndex >= 0) {
            spinnerStatus.setSelection(currentIndex)
        }

        // Handle status change
        spinnerStatus.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                val newStatus = statusOptions[position]
                if (newStatus != currentCaseStatus) {
                    // Show confirmation dialog
                    showStatusChangeConfirmation(newStatus)
                }
            }

            override fun onNothingSelected(parent: AdapterView<*>?) {
                // Do nothing
            }
        }
    }

    private fun showStatusChangeConfirmation(newStatus: String) {
        AlertDialog.Builder(this)
            .setTitle("Change Status")
            .setMessage("Change case status from '$currentCaseStatus' to '$newStatus'?")
            .setPositiveButton("Change") { _, _ ->
                updateCaseStatus(newStatus)
            }
            .setNegativeButton("Cancel") { dialog, _ ->
                // Reset spinner to current status
                val statusOptions = listOf("In Progress", "Complete", "Closed", "Pending")
                val currentIndex = statusOptions.indexOfFirst {
                    it.equals(currentCaseStatus, ignoreCase = true)
                }
                if (currentIndex >= 0) {
                    spinnerStatus.setSelection(currentIndex)
                }
                dialog.dismiss()
            }
            .show()
    }

    private fun updateCaseStatus(newStatus: String) {
        val queue = Volley.newRequestQueue(this)
        val url = ApiService.UPDATE_CASE_STATUS

        val progressDialog = ProgressDialog(this)
        progressDialog.setMessage("Updating status...")
        progressDialog.setCancelable(false)
        progressDialog.show()

        // Get user_id from session manager
        val sessionManager = SessionManager(this)
        val userId = sessionManager.getUserId()

        if (userId <= 0) {
            progressDialog.dismiss()
            Toast.makeText(this, "User not authenticated. Please login again.", Toast.LENGTH_LONG).show()
            // Redirect to login or handle appropriately
            return
        }

        val requestBody = JSONObject().apply {
            put("case_id", caseId)
            put("new_status", newStatus)
            put("user_id", userId) // Add user_id to the request
        }

        Log.d("CaseDetail", "Updating status - UserID: $userId, CaseID: $caseId, Status: $newStatus")

        val request = object : StringRequest(
            Method.POST,
            url,
            { response ->
                progressDialog.dismiss()
                Log.d("CaseDetail", "Response: $response")
                try {
                    val json = JSONObject(response)
                    val status = json.getString("status")

                    if (status == "success" || status == "warning") {
                        // Update current status
                        currentCaseStatus = newStatus

                        // Update UI
                        tvCaseStatus.text = newStatus
                        setStatusColor(newStatus)

                        // Show appropriate message
                        val message = if (status == "warning") {
                            json.optString("message", "Status updated with minor issues")
                        } else {
                            "Status updated to $newStatus"
                        }
                        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
                    } else {
                        val errorMsg = json.optString("message", "Failed to update status")
                        Toast.makeText(this, "Error: $errorMsg", Toast.LENGTH_LONG).show()

                        // Reset spinner to current status on error
                        resetSpinnerToCurrentStatus()
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    Toast.makeText(this, "Error parsing response", Toast.LENGTH_LONG).show()
                    resetSpinnerToCurrentStatus()
                }
            },
            { error ->
                progressDialog.dismiss()
                Toast.makeText(this, "Network error: ${error.message}", Toast.LENGTH_LONG).show()
                error.printStackTrace()
                resetSpinnerToCurrentStatus()
            }
        ) {
            override fun getBodyContentType(): String {
                return "application/json; charset=utf-8"
            }

            override fun getBody(): ByteArray {
                return requestBody.toString().toByteArray(Charsets.UTF_8)
            }
        }

        queue.add(request)
    }

    private fun resetSpinnerToCurrentStatus() {
        val statusOptions = listOf("In Progress", "Complete", "Closed", "Pending")
        val currentIndex = statusOptions.indexOfFirst {
            it.equals(currentCaseStatus, ignoreCase = true)
        }
        if (currentIndex >= 0) {
            spinnerStatus.setSelection(currentIndex)
        }
    }

    @SuppressLint("SetTextI18n")
    private fun loadCaseDetails(caseId: Int) {
        progressBar.visibility = View.VISIBLE
        recyclerEvidence.visibility = View.GONE
        tvNoEvidence.visibility = View.GONE

        val queue = Volley.newRequestQueue(this)
        val url = "${ApiService.CASE_DETAIL}?case_id=$caseId"

        val request = StringRequest(
            Request.Method.GET,
            url,
            { response ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false

                try {
                    println("API Response: $response")
                    val json = JSONObject(response)

                    if (json.getString("status") == "success") {
                        if (json.has("case")) {
                            val caseObj = json.getJSONObject("case")

                            tvCaseName.text = caseObj.optString("case_name", "Unknown Case")
                            tvCaseDescription.text = caseObj.optString("description", "No description available")
                            tvCaseStatus.text = caseObj.optString("status", "Unknown")
                            tvTotalEvidence.text = caseObj.optString("total_evidence", "0")
                            tvAssignedTo.text = caseObj.optString("assigned_to", "Not Assigned")
                            tvCaseNumber.text = "Case #$caseId"

                            tvEvidenceHeader.text = "Evidence (${caseObj.optString("total_evidence", "0")})"

                            setStatusColor(caseObj.optString("status", "Unknown"))

                            // Get current status
                            val currentStatus = caseObj.optString("status", "Unknown")

                            // Setup status spinner
                            setupStatusSpinner(currentStatus)
                        }

                        if (json.has("evidence") && !json.isNull("evidence")) {
                            val evidenceArray = json.getJSONArray("evidence")
                            if (evidenceArray.length() > 0) {
                                recyclerEvidence.visibility = View.VISIBLE
                                tvNoEvidence.visibility = View.GONE
                                evidenceAdapter.setData(evidenceArray)
                            } else {
                                recyclerEvidence.visibility = View.GONE
                                tvNoEvidence.visibility = View.VISIBLE
                                tvNoEvidence.text = "No Evidence Found"
                            }
                        } else {
                            recyclerEvidence.visibility = View.GONE
                            tvNoEvidence.visibility = View.VISIBLE
                            tvNoEvidence.text = "No Evidence Available"
                        }
                    } else {
                        val errorMsg = json.optString("message", "Unknown error")
                        Toast.makeText(this, "Error: $errorMsg", Toast.LENGTH_LONG).show()
                        tvNoEvidence.visibility = View.VISIBLE
                        tvNoEvidence.text = "Error loading evidence"
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    Toast.makeText(this, "Error parsing response: ${e.message}", Toast.LENGTH_LONG).show()
                    tvNoEvidence.visibility = View.VISIBLE
                    tvNoEvidence.text = "Error loading data"
                }
            },
            { error ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false
                Toast.makeText(this, "Network error: ${error.message}", Toast.LENGTH_LONG).show()
                error.printStackTrace()
                tvNoEvidence.visibility = View.VISIBLE
                tvNoEvidence.text = "Network error. Please try again."
            }
        )
        queue.add(request)
    }

    private fun setStatusColor(status: String) {
        val colorHex = when (status.lowercase(Locale.getDefault())) {
            "in progress" -> "#f9a825"
            "complete" -> "#2e7d32"
            "closed" -> "#c62828"
            "pending" -> "#ef6c00"
            "verified" -> "#2196F3"
            else -> "#777777"
        }

        try {
            val colorInt = android.graphics.Color.parseColor(colorHex)

            // Keep the rounded drawable
            val bg = tvCaseStatus.background.mutate()
            if (bg is android.graphics.drawable.GradientDrawable) {
                bg.setColor(colorInt)
            }

            tvCaseStatus.setTextColor(android.graphics.Color.WHITE)

        } catch (_: IllegalArgumentException) {
            // fallback color
            val bg = tvCaseStatus.background.mutate()
            if (bg is android.graphics.drawable.GradientDrawable) {
                bg.setColor(android.graphics.Color.parseColor("#777777"))
            }
        }
    }
}