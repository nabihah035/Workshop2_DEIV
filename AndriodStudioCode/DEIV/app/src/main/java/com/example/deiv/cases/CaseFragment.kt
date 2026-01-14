package com.example.deiv.cases

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.Context
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.inputmethod.InputMethodManager
import android.widget.*
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
import org.json.JSONArray
import org.json.JSONObject
import android.util.Log
import android.content.Intent
import com.android.volley.DefaultRetryPolicy
import com.google.android.material.bottomsheet.BottomSheetDialog
import java.util.*

class CaseFragment : Fragment() {

    private lateinit var recyclerCases: RecyclerView
    private lateinit var fabAddCase: ImageView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var progressBar: ProgressBar
    private lateinit var tvNoCases: TextView
    private lateinit var tvTotalCases: TextView
    private lateinit var etSearch: EditText
    private lateinit var btnApplyFilter: Button

    private var currentFilter = "All"
    private var allCases = JSONArray()
    private var isSearching = false

    private val caseAdapter = CaseAdapter { caseId ->
        val intent = Intent(requireContext(), CaseDetailActivity::class.java)
        intent.putExtra("case_id", caseId)
        startActivity(intent)
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
        tvTotalCases = view.findViewById(R.id.tvTotalCases)
        etSearch = view.findViewById(R.id.etSearch)

        val btnFilter = view.findViewById<ImageView>(R.id.btnFilter)
        btnFilter.setOnClickListener { showFilterDialog() }

        recyclerCases.layoutManager = LinearLayoutManager(context)
        recyclerCases.adapter = caseAdapter

        fabAddCase.setOnClickListener { showAddCaseDialog() }

        swipeRefresh.setOnRefreshListener { loadCases() }

        setupSearch()
        loadCases()

        return view
    }

    private fun setupSearch() {
        etSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                filterCases(s.toString().trim())
            }
        })

        etSearch.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == android.view.inputmethod.EditorInfo.IME_ACTION_SEARCH) {
                filterCases(etSearch.text.toString().trim())
                hideKeyboard()
                true
            } else false
        }
    }

    private fun hideKeyboard() {
        val imm = requireContext()
            .getSystemService(Context.INPUT_METHOD_SERVICE) as InputMethodManager
        imm.hideSoftInputFromWindow(etSearch.windowToken, 0)
    }

    private fun filterCases(searchQuery: String) {
        if (searchQuery.isEmpty()) {
            isSearching = false
            caseAdapter.setData(allCases)
            updateTotalCasesText(allCases.length())
            updateNoCasesVisibility(allCases.length())
            return
        }

        isSearching = true
        val filteredCases = JSONArray()

        for (i in 0 until allCases.length()) {
            val case = allCases.getJSONObject(i)
            val caseName = case.getString("case_name").lowercase(Locale.getDefault())
            if (caseName.contains(searchQuery.lowercase(Locale.getDefault()))) {
                filteredCases.put(case)
            }
        }

        caseAdapter.setData(filteredCases)
        updateTotalCasesText(filteredCases.length())
        updateNoCasesVisibility(filteredCases.length())

        if (filteredCases.length() == 0) {
            tvNoCases.text = "No cases found for \"$searchQuery\""
            tvNoCases.visibility = View.VISIBLE
        }
    }

    @SuppressLint("SetTextI18n")
    private fun updateNoCasesVisibility(caseCount: Int) {
        if (caseCount == 0) {
            if (!isSearching) {
                tvNoCases.text = "No cases found. Tap + to add your first case."
            }
            tvNoCases.visibility = View.VISIBLE
            recyclerCases.visibility = View.GONE
        } else {
            tvNoCases.visibility = View.GONE
            recyclerCases.visibility = View.VISIBLE
        }
    }

    @SuppressLint("SetTextI18n")
    private fun loadCases() {
        etSearch.text.clear()
        isSearching = false

        progressBar.visibility = View.VISIBLE
        tvNoCases.visibility = View.GONE
        tvTotalCases.visibility = View.GONE

        val session = SessionManager(requireContext())
        val userId = session.getUserId()

        if (userId <= 0) {
            progressBar.visibility = View.GONE
            swipeRefresh.isRefreshing = false
            Toast.makeText(requireContext(), "Please login to view cases", Toast.LENGTH_SHORT).show()
            startActivity(Intent(requireContext(), com.example.deiv.login.LoginActivity::class.java))
            requireActivity().finish()
            return
        }

        val url = "${ApiService.CASE_LIST}?user_id=$userId&status=$currentFilter"
        val queue = Volley.newRequestQueue(requireContext())

        val request = StringRequest(
            Request.Method.GET,
            url,
            { response ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false

                try {
                    val json = JSONObject(response)

                    if (json.getString("status") == "success") {
                        val totalCases = json.optInt("total_cases", 0)

                        if (json.has("cases") && !json.isNull("cases")) {
                            val casesArray = json.getJSONArray("cases")
                            allCases = casesArray

                            if (casesArray.length() > 0) {
                                val searchQuery = etSearch.text.toString().trim()
                                if (searchQuery.isNotEmpty()) {
                                    filterCases(searchQuery)
                                } else {
                                    tvNoCases.visibility = View.GONE
                                    recyclerCases.visibility = View.VISIBLE
                                    tvTotalCases.visibility = View.VISIBLE
                                    caseAdapter.setData(casesArray)
                                    updateTotalCasesText(totalCases)
                                }
                            } else {
                                updateNoCasesVisibility(0)
                                if (totalCases == 0) {
                                    tvTotalCases.visibility = View.VISIBLE
                                    updateTotalCasesText(0)
                                }
                            }
                        } else {
                            tvNoCases.visibility = View.VISIBLE
                            tvNoCases.text = "No cases available"
                            recyclerCases.visibility = View.GONE
                        }
                    } else {
                        val errorMsg = json.optString("message", "Unknown error")
                        tvNoCases.visibility = View.VISIBLE
                        tvNoCases.text = "Error: $errorMsg"
                        recyclerCases.visibility = View.GONE
                        Toast.makeText(requireContext(), errorMsg, Toast.LENGTH_LONG).show()
                    }
                } catch (e: Exception) {
                    tvNoCases.visibility = View.VISIBLE
                    tvNoCases.text = "Error parsing response"
                    recyclerCases.visibility = View.GONE
                }
            },
            { error ->
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false
                tvNoCases.visibility = View.VISIBLE
                tvNoCases.text = "Network error. Check connection."
                recyclerCases.visibility = View.GONE
                Toast.makeText(requireContext(), "Network error", Toast.LENGTH_SHORT).show()
            }
        )

        request.retryPolicy = DefaultRetryPolicy(
            10000,
            DefaultRetryPolicy.DEFAULT_MAX_RETRIES,
            DefaultRetryPolicy.DEFAULT_BACKOFF_MULT
        )

        queue.add(request)
    }

    @SuppressLint("SetTextI18n")
    private fun updateTotalCasesText(totalCases: Int) {
        tvTotalCases.text =
            if (totalCases == 1) "1 total case" else "$totalCases total cases"
    }

    @SuppressLint("UseKtx")
    private fun showAddCaseDialog() {
        val dialogView = LayoutInflater.from(requireContext())
            .inflate(R.layout.dialog_add_case, null)

        val etCaseName = dialogView.findViewById<EditText>(R.id.etCaseName)
        val etDescription = dialogView.findViewById<EditText>(R.id.etDescription)

        AlertDialog.Builder(requireContext())
            .setView(dialogView)
            .setPositiveButton("Create") { dialog, _ ->
                val caseName = etCaseName.text.toString().trim()
                val description = etDescription.text.toString().trim()
                if (caseName.isNotEmpty() && description.isNotEmpty()) {
                    createNewCase(caseName, description)
                }
                dialog.dismiss()
            }
            .setNegativeButton("Cancel") { dialog, _ -> dialog.dismiss() }
            .create()
            .apply {
                window?.setBackgroundDrawableResource(R.drawable.bg_dialog_rounded)
                show()
            }
    }

    private fun showFilterDialog() {
        etSearch.text.clear()

        val dialog = BottomSheetDialog(requireContext())
        val dialogView = LayoutInflater.from(requireContext())
            .inflate(R.layout.dialog_filter_case, null)

        dialog.setContentView(dialogView)
        dialog.window?.setBackgroundDrawableResource(android.R.color.transparent)
        dialog.window?.setDimAmount(0.6f)

        val spinnerStatus = dialogView.findViewById<Spinner>(R.id.spinnerStatus)
        val btnApplyFilter = dialogView.findViewById<Button>(R.id.btnApplyFilter) // ✅ FIX

        val statusOptions = arrayOf("All", "In Progress", "Complete", "Closed", "Pending")
        val adapter = ArrayAdapter(
            requireContext(),
            android.R.layout.simple_spinner_item,
            statusOptions
        )
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        spinnerStatus.adapter = adapter

        val currentPosition = statusOptions.indexOf(currentFilter)
        if (currentPosition >= 0) spinnerStatus.setSelection(currentPosition)

        btnApplyFilter.setOnClickListener {
            currentFilter = spinnerStatus.selectedItem.toString()
            etSearch.text.clear()
            loadCases()
            dialog.dismiss()
        }

        dialog.show()
        dialog.behavior.state =
            com.google.android.material.bottomsheet.BottomSheetBehavior.STATE_EXPANDED
    }

    private fun createNewCase(caseName: String, description: String) {
        val session = SessionManager(requireContext())
        val userId = session.getUserId()

        if (userId <= 0) {
            Toast.makeText(requireContext(), "User not authenticated", Toast.LENGTH_SHORT).show()
            return
        }

        val queue = Volley.newRequestQueue(requireContext())

        val request = object : StringRequest(
            Method.POST,
            ApiService.CASE_REGISTER,
            { response ->
                try {
                    val json = JSONObject(response)
                    if (json.getString("status") == "success") {
                        Toast.makeText(requireContext(), "Case created!", Toast.LENGTH_SHORT).show()
                        etSearch.text.clear()
                        loadCases()
                    } else {
                        Toast.makeText(
                            requireContext(),
                            "Failed: ${json.getString("message")}",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                } catch (e: Exception) {
                    Toast.makeText(requireContext(), "Error parsing response", Toast.LENGTH_SHORT).show()
                }
            },
            { error ->
                Toast.makeText(requireContext(), "Creation failed", Toast.LENGTH_LONG).show()
            }
        ) {
            override fun getParams(): Map<String, String> = hashMapOf(
                "case_name" to caseName,
                "description" to description,
                "status" to "Pending",
                "user_id" to userId.toString()
            )
        }

        queue.add(request)
    }
}
