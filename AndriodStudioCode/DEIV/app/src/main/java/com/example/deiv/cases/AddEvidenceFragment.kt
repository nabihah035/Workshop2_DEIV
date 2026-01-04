package com.example.deiv.cases

import android.annotation.SuppressLint
import android.content.Intent
import android.database.Cursor
import android.net.Uri
import android.os.Bundle
import android.provider.OpenableColumns
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.*
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.R
import com.example.deiv.api.ApiService
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.*

class AddEvidenceFragment : Fragment() {

    private lateinit var tvFileName: TextView
    private lateinit var btnPickFile: Button
    private lateinit var btnUpload: Button
    private lateinit var btnBack: ImageView
    private lateinit var imagePreview: ImageView
    private lateinit var btnOpenPdf: Button
    private lateinit var progressBar: ProgressBar
    private lateinit var tvTitle: TextView
    private lateinit var tvSubtitle: TextView

    private var selectedFileUri: Uri? = null
    private var selectedFileName: String = ""
    private var caseId: Int = -1

    @SuppressLint("SetTextI18n")
    private val pickMediaLauncher = registerForActivityResult(
        ActivityResultContracts.GetContent()
    ) { uri: Uri? ->
        uri?.let {
            selectedFileUri = it
            selectedFileName = getFileName(it)
            tvFileName.text = "Selected: $selectedFileName"
            previewSelectedFile(it)
        }
    }

    @SuppressLint("SetTextI18n")
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        val view = inflater.inflate(R.layout.fragment_add_evidence, container, false)

        // Initialize views
        tvTitle = view.findViewById(R.id.tvTitle)
        tvSubtitle = view.findViewById(R.id.tvSubtitle)
        tvFileName = view.findViewById(R.id.tvFileName)
        btnPickFile = view.findViewById(R.id.btnPickFile)
        btnUpload = view.findViewById(R.id.btnUpload)
        btnBack = view.findViewById(R.id.btnBack)
        imagePreview = view.findViewById(R.id.imagePreview)
        btnOpenPdf = view.findViewById(R.id.btnOpenPdf)
        progressBar = view.findViewById(R.id.progressBar)

        // Get case ID from arguments
        caseId = arguments?.getInt("case_id", -1) ?: -1

        if (caseId > 0) {
            tvSubtitle.text = "Case #$caseId - Upload evidence files"
        }

        setupListeners()

        return view
    }

    private fun setupListeners() {
        btnBack.setOnClickListener {
            findNavController().popBackStack()
        }

        btnPickFile.setOnClickListener {
            pickMediaLauncher.launch("*/*")
        }

        btnUpload.setOnClickListener {
            if (selectedFileUri != null) {
                uploadEvidence()
            } else {
                Toast.makeText(requireContext(), "Please select a file first", Toast.LENGTH_SHORT).show()
            }
        }

        btnOpenPdf.setOnClickListener {
            selectedFileUri?.let { uri ->
                val intent = Intent(Intent.ACTION_VIEW)
                intent.setDataAndType(uri, "application/pdf")
                intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                startActivity(intent)
            }
        }
    }

    private fun getFileName(uri: Uri): String {
        var name = "unknown"
        val cursor: Cursor? = requireContext().contentResolver.query(uri, null, null, null, null)

        cursor?.use {
            val index = it.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (it.moveToFirst() && index >= 0) {
                name = it.getString(index)
            }
        }
        return name
    }

    private fun previewSelectedFile(uri: Uri) {
        val mimeType = requireContext().contentResolver.getType(uri)

        when {
            mimeType?.startsWith("image") == true -> {
                imagePreview.setImageURI(uri)
                imagePreview.visibility = View.VISIBLE
                btnOpenPdf.visibility = View.GONE
            }
            mimeType == "application/pdf" -> {
                imagePreview.visibility = View.GONE
                btnOpenPdf.visibility = View.VISIBLE
            }
            else -> {
                imagePreview.visibility = View.GONE
                btnOpenPdf.visibility = View.GONE
                Toast.makeText(requireContext(), "File type not supported for preview", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun uploadEvidence() {
        if (caseId <= 0) {
            Toast.makeText(requireContext(), "Invalid case ID", Toast.LENGTH_SHORT).show()
            return
        }

        progressBar.visibility = View.VISIBLE
        btnUpload.isEnabled = false

        // Generate metadata
        val hashValue = generateRandomHash()
        val uploadDate = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())

        // Create request
        val queue = Volley.newRequestQueue(requireContext())
        // Use centralized API URL
        val url = ApiService.EVIDENCE_UPLOAD

        val request = object : StringRequest(
            Method.POST,
            url,
            { response ->
                progressBar.visibility = View.GONE
                btnUpload.isEnabled = true

                try {
                    val json = JSONObject(response)
                    if (json.getString("status") == "success") {
                        Toast.makeText(requireContext(), "Evidence uploaded successfully!", Toast.LENGTH_SHORT).show()

                        // Navigate back to case details
                        val bundle = Bundle()
                        bundle.putInt("case_id", caseId)
                        findNavController().navigate(
                            R.id.action_addEvidenceFragment_to_caseDetailFragment,
                            bundle
                        )
                    } else {
                        Toast.makeText(
                            requireContext(),
                            "Upload failed: ${json.getString("message")}",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                } catch (_: Exception) {
                    Toast.makeText(requireContext(), "Error parsing response", Toast.LENGTH_SHORT).show()
                }
            },
            { error ->
                progressBar.visibility = View.GONE
                btnUpload.isEnabled = true
                Toast.makeText(requireContext(), "Upload failed: ${error.message}", Toast.LENGTH_SHORT).show()
            }
        ) {
            override fun getParams(): Map<String, String> {
                val params = HashMap<String, String>()
                params["file_name"] = selectedFileName
                params["upload_date"] = uploadDate
                params["status"] = "Pending"
                params["hash_value"] = hashValue
                params["case_id"] = caseId.toString()
                return params
            }

            override fun getBodyContentType(): String {
                return "application/x-www-form-urlencoded"
            }
        }

        queue.add(request)
    }

    private fun generateRandomHash(): String {
        val randomBytes = ByteArray(32)
        Random().nextBytes(randomBytes)
        return randomBytes.joinToString("") { "%02x".format(it) }
    }
}