package com.example.deiv.login

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.example.deiv.R
import com.example.deiv.api.ApiService
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.concurrent.Executors

class ForgotPasswordActivity : AppCompatActivity() {

    private lateinit var etUsername: EditText
    private lateinit var etEmail: EditText
    private lateinit var etNewPassword: EditText
    private lateinit var btnReset: Button
    private lateinit var tvBackToLogin: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_forgot_password)

        etUsername = findViewById(R.id.etUsername)
        etEmail = findViewById(R.id.etEmail)
        etNewPassword = findViewById(R.id.etNewPassword)
        btnReset = findViewById(R.id.btnReset)
        tvBackToLogin = findViewById(R.id.tvBackToLogin)

        btnReset.setOnClickListener {
            val username = etUsername.text.toString().trim()
            val email = etEmail.text.toString().trim()
            val newPassword = etNewPassword.text.toString().trim()

            if (username.isEmpty() || email.isEmpty() || newPassword.isEmpty()) {
                Toast.makeText(this, "Please fill all fields", Toast.LENGTH_SHORT).show()
            } else {
                resetPassword(username, email, newPassword)
            }
        }

        tvBackToLogin.setOnClickListener {
            finish()
        }
    }

    private fun resetPassword(username: String, email: String, newPass: String) {
        val executor = Executors.newSingleThreadExecutor()
        val handler = Handler(Looper.getMainLooper())

        btnReset.isEnabled = false
        btnReset.text = "Processing..."

        executor.execute {
            try {
                // 1. Prepare Data
                val postData = "username=${URLEncoder.encode(username, "UTF-8")}" +
                        "&email=${URLEncoder.encode(email, "UTF-8")}" +
                        "&new_password=${URLEncoder.encode(newPass, "UTF-8")}"

                // 2. Connect
                val url = URL(ApiService.FORGOT_PASSWORD)
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")

                // 3. Send Request
                conn.outputStream.use { it.write(postData.toByteArray()) }

                // 4. Handle Response
                val responseCode = conn.responseCode
                if (responseCode == HttpURLConnection.HTTP_OK) {
                    val reader = BufferedReader(InputStreamReader(conn.inputStream))
                    val response = reader.readText()
                    reader.close()

                    // Log the response to Logcat
                    Log.d("FORGOT_PASSWORD", "Response: $response")

                    handler.post {
                        handleResponse(response)
                    }
                } else {
                    handler.post {
                        Toast.makeText(this, "Server error: $responseCode", Toast.LENGTH_SHORT).show()
                        resetButton()
                    }
                }
            } catch (e: Exception) {
                handler.post {
                    Toast.makeText(this, "Network error: ${e.message}", Toast.LENGTH_SHORT).show()
                    resetButton()
                }
            }
        }
    }

    private fun handleResponse(response: String) {
        try {
            // Check if PHP sent an error (HTML) instead of JSON
            if (response.contains("<br") || response.contains("<!DOCTYPE") || response.contains("<html")) {
                // For debugging, show a bit of the actual response
                val debugMsg = if (response.length > 50) response.substring(0, 50) else response
                Toast.makeText(this, "PHP Error: $debugMsg", Toast.LENGTH_LONG).show()
                resetButton()
                return
            }

            val json = JSONObject(response)
            val status = json.optString("status", "error")
            val message = json.optString("message", "Unknown error")

            Toast.makeText(this, message, Toast.LENGTH_LONG).show()
            if (status == "success") {
                finish()
            } else {
                resetButton()
            }
        } catch (e: Exception) {
            Toast.makeText(this, "Response error: ${e.message}", Toast.LENGTH_SHORT).show()
            resetButton()
        }
    }

    private fun resetButton() {
        btnReset.isEnabled = true
        btnReset.text = "Reset Password"
    }
}
