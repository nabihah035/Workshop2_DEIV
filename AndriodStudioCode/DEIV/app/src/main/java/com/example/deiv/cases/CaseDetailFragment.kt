package com.example.deiv.cases

import android.annotation.SuppressLint
import android.os.Bundle
import androidx.fragment.app.Fragment
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.ImageView
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import androidx.recyclerview.widget.LinearLayoutManager
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.R
import com.example.deiv.api.ApiService
import org.json.JSONObject
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.google.android.material.floatingactionbutton.FloatingActionButton
import android.widget.Toast
import java.util.Locale

class CaseDetailFragment : Fragment() {

    private lateinit var recyclerEvidence: RecyclerView
    private lateinit var progressBar: ProgressBar
    private lateinit var tvCaseName: TextView
    private lateinit var tvCaseDescription: TextView
    private lateinit var tvCaseStatus: TextView
    private lateinit var tvTotalEvidence: TextView
    private lateinit var tvAssignedTo: TextView
    private lateinit var tvCaseNumber: TextView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var btnBack: ImageView
    private lateinit var tvNoEvidence: TextView

    private lateinit var evidenceAdapter: EvidenceAdapter
    private var caseId: Int = -1

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        evidenceAdapter = EvidenceAdapter()
    }

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        val view = inflater.inflate(R.layout.fragment_case_detail, container, false)

        btnBack = view.findViewById(R.id.btnBack)
        recyclerEvidence = view.findViewById(R.id.recyclerEvidence)
        progressBar = view.findViewById(R.id.progressBar)
        tvCaseName = view.findViewById(R.id.tvCaseName)
        tvCaseDescription = view.findViewById(R.id.tvCaseDescription)
        tvCaseStatus = view.findViewById(R.id.tvCaseStatus)
        tvTotalEvidence = view.findViewById(R.id.tvTotalEvidence)
        tvAssignedTo = view.findViewById(R.id.tvAssignedTo)
        tvCaseNumber = view.findViewById(R.id.tvCaseNumber)
        swipeRefresh = view.findViewById(R.id.swipeRefresh)
        tvNoEvidence = view.findViewById(R.id.tvNoEvidence)

        val fabAddEvidence: FloatingActionButton = view.findViewById(R.id.fabAddEvidence)

        recyclerEvidence.layoutManager = LinearLayoutManager(requireContext())
        recyclerEvidence.adapter = evidenceAdapter

        fabAddEvidence.setOnClickListener {
            if (caseId > 0) {
                val bundle = Bundle()
                bundle.putInt("case_id", caseId)
                findNavController().navigate(R.id.action_caseDetailFragment_to_addEvidenceFragment, bundle)
            } else {
                Toast.makeText(requireContext(), "Invalid Case ID", Toast.LENGTH_SHORT).show()
            }
        }

        btnBack.setOnClickListener {
            findNavController().popBackStack()
        }

        swipeRefresh.setOnRefreshListener {
            loadCaseDetails(caseId)
        }

        return view
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        caseId = arguments?.getInt("case_id", -1) ?: -1

        if (caseId > 0) {
            loadCaseDetails(caseId)
        } else {
            Toast.makeText(requireContext(), "Invalid Case ID", Toast.LENGTH_SHORT).show()
            findNavController().popBackStack()
        }
    }

    @SuppressLint("SetTextI18n")
    private fun loadCaseDetails(caseId: Int) {
        progressBar.visibility = View.VISIBLE
        recyclerEvidence.visibility = View.GONE
        tvNoEvidence.visibility = View.GONE

        val queue = Volley.newRequestQueue(requireContext())
        // Use centralized API URL
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
                        // First check if the response contains case details
                        if (json.has("case")) {
                            val caseObj = json.getJSONObject("case")

                            // Update UI with case details
                            tvCaseName.text = caseObj.optString("case_name", "Unknown Case")
                            tvCaseDescription.text = caseObj.optString("description", "No description available")
                            tvCaseStatus.text = caseObj.optString("status", "Unknown")
                            tvTotalEvidence.text = caseObj.optString("total_evidence", "0")
                            tvAssignedTo.text = caseObj.optString("assigned_to", "Not Assigned")
                            tvCaseNumber.text = "Case #$caseId"

                            setStatusColor(caseObj.optString("status", "Unknown"))
                        }

                        // Handle evidence list
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
                        Toast.makeText(requireContext(), "Error: $errorMsg", Toast.LENGTH_LONG).show()
                        tvNoEvidence.visibility = View.VISIBLE
                        tvNoEvidence.text = "Error loading evidence"
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    Toast.makeText(requireContext(), "Error parsing response: ${e.message}", Toast.LENGTH_LONG).show()
                    tvNoEvidence.visibility = View.VISIBLE
                    tvNoEvidence.text = "Error loading data"
                }
            },
            { error ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false
                Toast.makeText(requireContext(), "Network error: ${error.message}", Toast.LENGTH_LONG).show()
                error.printStackTrace()
                tvNoEvidence.visibility = View.VISIBLE
                tvNoEvidence.text = "Network error. Please try again."
            }
        )
        queue.add(request)
    }

    @SuppressLint("UseKtx")
    private fun setStatusColor(status: String) {
        val colorHex = when (status.lowercase(Locale.getDefault())) {
            "in progress" -> "#f9a825"  // yellow/orange
            "complete" -> "#2e7d32"     // green
            "closed" -> "#c62828"       // red
            "pending" -> "#ef6c00"      // orange
            "verified" -> "#2196F3"     // blue for verified
            else -> "#777777"           // default gray
        }

        try {
            val colorInt = android.graphics.Color.parseColor(colorHex)
            tvCaseStatus.setBackgroundColor(colorInt)
            tvCaseStatus.setTextColor(if (status.lowercase(Locale.getDefault()) in listOf(
                    "complete",
                    "closed"
                )) {
                android.graphics.Color.WHITE
            } else {
                android.graphics.Color.WHITE
            })
        } catch (_: IllegalArgumentException) {
            tvCaseStatus.setBackgroundColor(android.graphics.Color.parseColor("#777777"))
            tvCaseStatus.setTextColor(android.graphics.Color.WHITE)
        }
    }
}