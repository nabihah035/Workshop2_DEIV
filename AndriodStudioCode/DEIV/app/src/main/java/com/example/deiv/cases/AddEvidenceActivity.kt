package com.example.deiv.cases

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.Intent
import android.database.Cursor
import android.net.Uri
import android.os.Bundle
import android.provider.OpenableColumns
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.widget.*
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.example.deiv.login.SessionManager
import com.example.deiv.MainActivity
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.RequestBody.Companion.asRequestBody
import org.json.JSONObject
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.util.concurrent.TimeUnit

class AddEvidenceActivity : AppCompatActivity() {

    // UI Components
    private lateinit var tvFileName: TextView
    private lateinit var tvCaseName: TextView
    private lateinit var tvCaseId: TextView
    private lateinit var btnPickFile: Button
    private lateinit var btnUpload: Button
    private lateinit var btnBack: ImageView
    private lateinit var imagePreview: ImageView
    private lateinit var btnOpenPdf: Button
    private lateinit var progressBar: ProgressBar
    private lateinit var tvTitle: TextView
    private lateinit var tvSubtitle: TextView

    // Data Variables
    private var selectedFileUri: Uri? = null
    private var selectedFileName: String = ""
    private var caseId: Int = -1
    private var caseName: String = ""
    private lateinit var sessionManager: SessionManager

    // Setup OkHttp Client (From your Fragment logic)
    private val client = OkHttpClient.Builder()
        .connectTimeout(60, TimeUnit.SECONDS)
        .writeTimeout(60, TimeUnit.SECONDS)
        .readTimeout(120, TimeUnit.SECONDS)
        .build()

    // File Picker Launcher
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

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_add_evidence)

        // Initialize SessionManager
        sessionManager = SessionManager(this)

        // Initialize Views
        initViews()

        // Get Intent Data
        caseId = intent.getIntExtra("case_id", -1)
        caseName = intent.getStringExtra("case_name") ?: "Unknown Case"

        // Set UI Data
        tvCaseName.text = caseName
        tvCaseId.text = "Case #$caseId"

        checkLoginStatus()
        setupListeners()
    }

    private fun initViews() {
        tvTitle = findViewById(R.id.tvTitle)
        tvSubtitle = findViewById(R.id.tvSubtitle)
        tvCaseName = findViewById(R.id.tvCaseName)
        tvCaseId = findViewById(R.id.tvCaseId)
        tvFileName = findViewById(R.id.tvFileName)
        btnPickFile = findViewById(R.id.btnPickFile)
        btnUpload = findViewById(R.id.btnUpload)
        btnBack = findViewById(R.id.btnBack)
        imagePreview = findViewById(R.id.imagePreview)
        btnOpenPdf = findViewById(R.id.btnOpenPdf)
        progressBar = findViewById(R.id.progressBar)
    }

    private fun setupListeners() {
        btnBack.setOnClickListener {
            finish() // Activities use finish(), Fragments use popBackStack()
        }

        btnPickFile.setOnClickListener {
            pickMediaLauncher.launch("*/*")
        }

        btnOpenPdf.setOnClickListener {
            selectedFileUri?.let { uri ->
                val intent = Intent(Intent.ACTION_VIEW)
                intent.setDataAndType(uri, "application/pdf")
                intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                startActivity(intent)
            }
        }

        btnUpload.setOnClickListener {
            if (selectedFileUri != null) {
                uploadEvidenceWithAiDetection()
            } else {
                Toast.makeText(this, "Please select a file first", Toast.LENGTH_SHORT).show()
            }
        }
    }

    // --- LOGIC MOVED FROM FRAGMENT: CREATE TEMP FILE ---
    private fun getFileFromUri(uri: Uri): File? {
        val contentResolver = contentResolver
        val tempFile = File(cacheDir, selectedFileName)
        try {
            val inputStream = contentResolver.openInputStream(uri)
            val outputStream = FileOutputStream(tempFile)
            inputStream?.copyTo(outputStream)
            inputStream?.close()
            outputStream.close()
            return tempFile
        } catch (e: IOException) {
            Log.e("AddEvidenceActivity", "Failed to create temp file", e)
            return null
        }
    }

    // --- LOGIC MOVED FROM FRAGMENT: UPLOAD WITH OKHTTP ---
    private fun uploadEvidenceWithAiDetection() {
        if (caseId <= 0 || selectedFileUri == null) {
            Toast.makeText(this, "Invalid case ID or no file selected", Toast.LENGTH_SHORT).show()
            return
        }

        val userId = sessionManager.getUserId()
        progressBar.visibility = View.VISIBLE
        btnUpload.isEnabled = false

        // Create the temp file for upload
        val file = getFileFromUri(selectedFileUri!!)
        if (file == null) {
            Toast.makeText(this, "Failed to read file.", Toast.LENGTH_SHORT).show()
            progressBar.visibility = View.GONE
            btnUpload.isEnabled = true
            return
        }

        val mimeType = contentResolver.getType(selectedFileUri!!)

        // Build Multipart Body
        val requestBody = MultipartBody.Builder()
            .setType(MultipartBody.FORM)
            .addFormDataPart("case_id", caseId.toString())
            .addFormDataPart("user_id", userId.toString())
            // This uploads the actual file binary
            .addFormDataPart("file", selectedFileName, file.asRequestBody(mimeType?.toMediaTypeOrNull()))
            .build()

        val request = Request.Builder()
            .url(ApiService.EVIDENCE_UPLOAD)
            .post(requestBody)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                runOnUiThread {
                    progressBar.visibility = View.GONE
                    btnUpload.isEnabled = true
                    showCustomDialog(false, "Network Error", "Failed to connect: ${e.message}", false)
                }
            }

            override fun onResponse(call: Call, response: Response) {
                val responseBody = response.body?.string()
                runOnUiThread {
                    progressBar.visibility = View.GONE
                    btnUpload.isEnabled = true

                    if (!response.isSuccessful) {
                        Log.e("AddEvidenceActivity", "Server Error: ${response.code} - $responseBody")
                        showCustomDialog(false, "Server Error", "Code: ${response.code}", false)
                        return@runOnUiThread
                    }

                    if (responseBody == null) {
                        Log.e("AddEvidenceActivity", "Response body is null")
                        showCustomDialog(false, "Parsing Error", "Empty response from server.", false)
                        return@runOnUiThread
                    }

                    try {
                        val json = JSONObject(responseBody)
                        val status = json.getString("status")
                        val message = json.getString("message")

                        when (status) {
                            "success" -> {
                                // Evidence valid and saved
                                showCustomDialog(true, "Evidence Verified", message, true)
                            }
                            "tampered" -> {
                                // Evidence tampered but saved as alert
                                showCustomDialog(false, "Security Alert", message, true)
                            }
                            else -> {
                                // Generic error
                                showCustomDialog(false, "Upload Failed", message, false)
                            }
                        }
                    } catch (e: Exception) {
                        Log.e("AddEvidenceActivity", "Error parsing JSON: '$responseBody'", e)
                        showCustomDialog(false, "Parsing Error", "Invalid response from server. Check logs for details.", false)
                    }
                }
            }
        })
    }

    // --- CUSTOM DIALOG (Adapted for Activity) ---
    private fun showCustomDialog(isSuccess: Boolean, title: String, message: String, shouldClose: Boolean) {
        val dialogView = LayoutInflater.from(this).inflate(R.layout.dialog_upload_result, null)

        val icon = dialogView.findViewById<ImageView>(R.id.dialogIcon)
        val tvTitle = dialogView.findViewById<TextView>(R.id.dialogTitle)
        val tvMessage = dialogView.findViewById<TextView>(R.id.dialogMessage)
        val btnAction = dialogView.findViewById<Button>(R.id.dialogButton)

        tvTitle.text = title
        tvMessage.text = message

        if (isSuccess) {
            icon.setImageResource(R.drawable.ic_check_circle)
            icon.setColorFilter(getColor(android.R.color.holo_green_dark))
            btnAction.setBackgroundColor(getColor(android.R.color.holo_green_dark))
            btnAction.text = "CONTINUE"
        } else {
            icon.setImageResource(android.R.drawable.ic_dialog_alert)
            icon.setColorFilter(getColor(android.R.color.holo_red_dark))
            btnAction.setBackgroundColor(getColor(android.R.color.holo_red_dark))
            btnAction.text = if (shouldClose) "ACKNOWLEDGE" else "TRY AGAIN"
        }

        val builder = AlertDialog.Builder(this)
        builder.setView(dialogView)
        builder.setCancelable(false)
        val dialog = builder.create()
        dialog.window?.setBackgroundDrawableResource(android.R.color.transparent)

        btnAction.setOnClickListener {
            dialog.dismiss()
            if (shouldClose) {
                // Return result OK so CaseDetail can refresh
                setResult(RESULT_OK)
                finish()
            }
        }

        dialog.show()
    }

    // --- HELPER FUNCTIONS ---
    private fun getFileName(uri: Uri): String {
        var name = "unknown"
        val cursor: Cursor? = contentResolver.query(uri, null, null, null, null)
        cursor?.use {
            if (it.moveToFirst()) {
                val index = it.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                if (index >= 0) name = it.getString(index)
            }
        }
        return name
    }

    private fun previewSelectedFile(uri: Uri) {
        val mimeType = contentResolver.getType(uri)
        if (mimeType?.startsWith("image") == true) {
            imagePreview.setImageURI(uri)
            imagePreview.visibility = View.VISIBLE
            btnOpenPdf.visibility = View.GONE
        } else if (mimeType == "application/pdf") {
            imagePreview.visibility = View.GONE
            btnOpenPdf.visibility = View.VISIBLE
        } else {
            imagePreview.visibility = View.GONE
            btnOpenPdf.visibility = View.GONE
        }
    }

    private fun checkLoginStatus() {
        if (!sessionManager.isLoggedIn()) {
            Toast.makeText(this, "Login required", Toast.LENGTH_SHORT).show()
            val intent = Intent(this, MainActivity::class.java)
            intent.flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_NEW_TASK
            startActivity(intent)
            finish()
        }
    }
}