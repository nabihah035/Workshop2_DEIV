package com.example.deiv.login

import android.os.Bundle
import android.os.Handler
import android.os.Looper
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
    private lateinit var btnReset: Button
    private lateinit var tvBackToLogin: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_forgot_password)

        etUsername = findViewById(R.id.etUsername)
        btnReset = findViewById(R.id.btnReset)
        tvBackToLogin = findViewById(R.id.tvBackToLogin)

        btnReset.setOnClickListener {
            val username = etUsername.text.toString().trim()
            if (username.isEmpty()) {
                Toast.makeText(this, "Please enter your username", Toast.LENGTH_SHORT).show()
            } else {
                resetPassword(username)
            }
        }

        tvBackToLogin.setOnClickListener {
            finish()
        }
    }

    private fun resetPassword(username: String) {
        val executor = Executors.newSingleThreadExecutor()
        val handler = Handler(Looper.getMainLooper())

        btnReset.isEnabled = false
        btnReset.text = "Processing..."

        executor.execute {
            try {
                val postData = "username=${URLEncoder.encode(username, "UTF-8")}"
                val url = URL(ApiService.FORGOT_PASSWORD)
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")

                conn.outputStream.use { it.write(postData.toByteArray()) }

                val responseCode = conn.responseCode
                if (responseCode == HttpURLConnection.HTTP_OK) {
                    val reader = BufferedReader(InputStreamReader(conn.inputStream))
                    val response = reader.readText()
                    reader.close()

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
            val json = JSONObject(response)
            val status = json.getString("status")
            val message = json.getString("message")

            Toast.makeText(this, message, Toast.LENGTH_LONG).show()
            if (status == "success") {
                finish()
            } else {
                resetButton()
            }
        } catch (e: Exception) {
            Toast.makeText(this, "Error: ${e.message}", Toast.LENGTH_SHORT).show()
            resetButton()
        }
    }

    private fun resetButton() {
        btnReset.isEnabled = true
        btnReset.text = "Reset Password"
    }
}
