package com.example.deiv.ai

import android.app.Activity
import android.content.ContentValues
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.MediaStore
import android.provider.OpenableColumns
import android.util.Log
import android.view.View
import android.webkit.MimeTypeMap
import android.widget.*
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import com.example.deiv.R
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.io.InputStream
import java.util.concurrent.TimeUnit

class AiFragment : Fragment(R.layout.fragment_ai) {

    // --- CONFIGURATION ---
    private val API_BASE_URL = "http://192.168.0.128:5000"  //tukar ikut ip laptop nabil
    private val ENDPOINT_PREDICT_JSON = "/predict"
    private val ENDPOINT_DOWNLOAD_REPORT = "/generate_report"

    private var selectedFileUri: Uri? = null

    // UI Components
    private lateinit var buttonSelectFile: Button
    private lateinit var buttonUpload: Button
    private lateinit var textViewSelectedFile: TextView
    private lateinit var textViewResult: TextView
    private lateinit var imageViewPreview: ImageView
    private lateinit var radioShowResult: RadioButton
    private lateinit var radioDownloadReport: RadioButton

    // OkHttp Client
    private val client = OkHttpClient.Builder()
        .connectTimeout(60, TimeUnit.SECONDS)
        .writeTimeout(60, TimeUnit.SECONDS)
        .readTimeout(120, TimeUnit.SECONDS)
        .build()

    // File Picker Launcher
    private val filePickerLauncher = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            result.data?.data?.let { uri ->
                selectedFileUri = uri
                updateUiWithFile(uri)
            }
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        // 1. Initialize Views
        buttonSelectFile = view.findViewById(R.id.buttonSelectFile)
        buttonUpload = view.findViewById(R.id.buttonUpload)
        textViewSelectedFile = view.findViewById(R.id.textViewSelectedFile)
        textViewResult = view.findViewById(R.id.textViewResult)
        imageViewPreview = view.findViewById(R.id.imageViewPreview)
        radioShowResult = view.findViewById(R.id.radioShowResult)
        radioDownloadReport = view.findViewById(R.id.radioDownloadReport)

        // 2. Button Listeners
        buttonSelectFile.setOnClickListener {
            val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
                type = "*/*"
                putExtra(Intent.EXTRA_MIME_TYPES, arrayOf("image/*", "application/pdf", "video/*"))
            }
            filePickerLauncher.launch(Intent.createChooser(intent, "Select Evidence"))
        }

        buttonUpload.setOnClickListener {
            if (selectedFileUri != null) {
                if (radioShowResult.isChecked) fetchJsonResult() else uploadAndDownloadReport()
            } else {
                Toast.makeText(requireContext(), "Please select a file first", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun updateUiWithFile(uri: Uri) {
        val fileName = getFileName(uri)
        textViewSelectedFile.text = fileName

        // Update Preview
        imageViewPreview.alpha = 1.0f
        imageViewPreview.colorFilter = null

        val mimeType = requireContext().contentResolver.getType(uri)
        when {
            mimeType?.startsWith("image") == true -> imageViewPreview.setImageURI(uri)
            mimeType?.startsWith("video") == true -> imageViewPreview.setImageResource(android.R.drawable.ic_media_play)
            else -> imageViewPreview.setImageResource(android.R.drawable.ic_menu_save)
        }

        // Enable Button
        buttonUpload.isEnabled = true
        buttonUpload.alpha = 1.0f
        textViewResult.text = "System Ready. Initialize Scan."
        textViewResult.setBackgroundColor(requireContext().getColor(android.R.color.holo_blue_light))
    }

    // --- NETWORK LOGIC ---

    private fun fetchJsonResult() {
        updateStatus("Scanning Digital Signature...", true)

        Thread {
            try {
                val body = createMultipartBody() ?: return@Thread
                val request = Request.Builder().url(API_BASE_URL + ENDPOINT_PREDICT_JSON).post(body).build()

                client.newCall(request).execute().use { response ->
                    val resultText = if (response.isSuccessful) parseResult(response.body?.string()) else "Error: ${response.code}"
                    activity?.runOnUiThread { updateStatus(resultText, false) }
                }
            } catch (e: Exception) {
                Log.e("AI_NET", "Error", e)
                activity?.runOnUiThread { updateStatus("Connection Failed (Check IP)", false) }
            }
        }.start()
    }

    private fun uploadAndDownloadReport() {
        updateStatus("Compiling Forensic Report...", true)

        Thread {
            try {
                val body = createMultipartBody() ?: return@Thread
                val request = Request.Builder().url(API_BASE_URL + ENDPOINT_DOWNLOAD_REPORT).post(body).build()

                client.newCall(request).execute().use { response ->
                    if (response.isSuccessful) {
                        val filename = "ForensicReport_${System.currentTimeMillis()}.pdf"
                        val success = response.body?.byteStream()?.let { saveFileToDownloads(it, filename) } ?: false
                        activity?.runOnUiThread {
                            updateStatus(if (success) "Report Saved to Downloads" else "Save Failed", false)
                        }
                    } else {
                        activity?.runOnUiThread { updateStatus("Server Error: ${response.code}", false) }
                    }
                }
            } catch (e: Exception) {
                Log.e("AI_NET", "Error", e)
                activity?.runOnUiThread { updateStatus("Connection Failed", false) }
            }
        }.start()
    }

    // --- HELPER FUNCTIONS ---

    private fun createMultipartBody(): MultipartBody? {
        val uri = selectedFileUri ?: return null
        val contentResolver = requireContext().contentResolver

        val inputStream = contentResolver.openInputStream(uri) ?: return null
        val fileBytes = inputStream.readBytes()
        inputStream.close()

        val mimeType = contentResolver.getType(uri) ?: "application/octet-stream"
        val requestBody = fileBytes.toRequestBody(mimeType.toMediaTypeOrNull())

        return MultipartBody.Builder()
            .setType(MultipartBody.FORM)
            .addFormDataPart("file", getFileName(uri), requestBody)
            .build()
    }

    private fun parseResult(json: String?): String {
        return try {
            val jsonObj = JSONObject(json ?: "")
            val pred = jsonObj.optString("prediction", "N/A")
            val conf = jsonObj.optDouble("confidence", 0.0)
            "VERDICT: $pred\nRISK LEVEL: ${"%.1f".format(conf * 100)}%"
        } catch (e: Exception) {
            "Error parsing result"
        }
    }

    private fun updateStatus(text: String, isLoading: Boolean) {
        textViewResult.text = text
        if (isLoading) {
            textViewResult.setBackgroundColor(0xFFE3F2FD.toInt()) // Light Blue
            textViewResult.setTextColor(0xFF0D47A1.toInt()) // Dark Blue
        } else {
            textViewResult.setBackgroundColor(0xFFE8F5E9.toInt()) // Light Green
            textViewResult.setTextColor(0xFF1B5E20.toInt()) // Dark Green
        }
    }

    private fun getFileName(uri: Uri): String {
        var result: String? = null
        if (uri.scheme == "content") {
            val cursor = requireContext().contentResolver.query(uri, null, null, null, null)
            cursor?.use {
                if (it.moveToFirst()) {
                    val index = it.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                    if (index >= 0) result = it.getString(index)
                }
            }
        }
        if (result == null) {
            result = uri.path
            val cut = result?.lastIndexOf('/')
            if (cut != -1) result = result?.substring(cut!! + 1)
        }
        return result ?: "unknown_file"
    }

    private fun saveFileToDownloads(inputStream: InputStream, filename: String): Boolean {
        return try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.MediaColumns.DISPLAY_NAME, filename)
                    put(MediaStore.MediaColumns.MIME_TYPE, "application/pdf")
                    put(MediaStore.MediaColumns.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS)
                }
                val uri = requireContext().contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values) ?: return false
                requireContext().contentResolver.openOutputStream(uri)?.use { inputStream.copyTo(it) }
                true
            } else {
                val downloadsDir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS)
                val file = File(downloadsDir, filename)
                FileOutputStream(file).use { inputStream.copyTo(it) }
                true
            }
        } catch (e: Exception) { false }
    }
}