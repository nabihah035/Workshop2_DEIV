package com.example.deiv.login

import android.Manifest
import android.annotation.SuppressLint
import android.content.DialogInterface
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.concurrent.Executors
import com.example.deiv.R
import com.example.deiv.MainActivity
import com.example.deiv.api.ApiService
import android.util.Log

class LoginActivity : AppCompatActivity() {
    private var etUsername: EditText? = null
    private var etPassword: EditText? = null
    private var btnLogin: Button? = null
    private var tvCreateAccount: TextView? = null
    private var tvForgotPassword: TextView? = null
    private lateinit var sessionManager: SessionManager

    @SuppressLint("ObsoleteSdkInt")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        // Initialize SessionManager
        sessionManager = SessionManager(this)

        // Check if already logged in
        if (sessionManager.isLoggedIn()) {
            val intent = Intent(this, MainActivity::class.java)
            startActivity(intent)
            finish()
            return
        }

        // Request notification permission (Android 13+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(
                    this,
                    Manifest.permission.POST_NOTIFICATIONS
                ) != PackageManager.PERMISSION_GRANTED
            ) {
                ActivityCompat.requestPermissions(
                    this,
                    arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                    101
                )
            }
        }

        // Initialize views
        etUsername = findViewById(R.id.etUsername)
        etPassword = findViewById(R.id.etPassword)
        btnLogin = findViewById(R.id.btnLogin)
        tvCreateAccount = findViewById(R.id.tvCreateAccount)
        tvForgotPassword = findViewById(R.id.tvForgotPassword)

        btnLogin?.setOnClickListener {
            val user = etUsername?.text?.toString()?.trim() ?: ""
            val pass = etPassword?.text?.toString()?.trim() ?: ""

            if (user.isEmpty() || pass.isEmpty()) {
                Toast.makeText(this, "Enter username and password", Toast.LENGTH_SHORT).show()
            } else {
                loginWithPHP(user, pass)
            }
        }

        tvCreateAccount?.setOnClickListener {
            startActivity(Intent(applicationContext, RegisterActivity::class.java))
        }

        tvForgotPassword?.setOnClickListener {
            startActivity(Intent(applicationContext, ForgotPasswordActivity::class.java))
        }
    }

    @SuppressLint("SetTextI18n")
    private fun loginWithPHP(username: String, password: String) {
        val executor = Executors.newSingleThreadExecutor()
        val handler = Handler(Looper.getMainLooper())

        // Show loading
        btnLogin?.isEnabled = false
        btnLogin?.text = "Logging in..."

        executor.execute {
            try {
                // 1. Prepare Data
                val postData = "username=${URLEncoder.encode(username, "UTF-8")}" +
                        "&password=${URLEncoder.encode(password, "UTF-8")}"

                // 2. Connect using ApiService.LOGIN
                val url = URL(ApiService.LOGIN)
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")
                conn.setRequestProperty("Charset", "UTF-8")

                // 3. Write post data
                conn.outputStream.use { os ->
                    os.write(postData.toByteArray())
                    os.flush()
                }

                // 4. Check response code
                val responseCode = conn.responseCode
                if (responseCode != HttpURLConnection.HTTP_OK) {
                    handler.post {
                        Toast.makeText(
                            this@LoginActivity,
                            "Server Error: HTTP $responseCode",
                            Toast.LENGTH_LONG
                        ).show()
                        resetLoginButton()
                    }
                    return@execute
                }

                // 5. Read Response
                val reader = BufferedReader(InputStreamReader(conn.inputStream))
                val response = StringBuilder()
                var line: String?
                while (reader.readLine().also { line = it } != null) {
                    response.append(line)
                }
                reader.close()

                // 6. Handle JSON on Main Thread
                handler.post {
                    handleLoginResponse(response.toString(), username)
                }

            } catch (e: Exception) {
                e.printStackTrace()
                handler.post {
                    Toast.makeText(
                        this@LoginActivity,
                        "Network Error: ${e.localizedMessage ?: "Check server connection"}",
                        Toast.LENGTH_LONG
                    ).show()
                    resetLoginButton()
                }
            }
        }
    }

    private fun handleLoginResponse(response: String, username: String) {
        try {
            // Check if PHP sent an error (HTML) instead of JSON
            if (response.contains("<br") || response.contains("<!DOCTYPE") || response.contains("<html")) {
                Toast.makeText(
                    this@LoginActivity,
                    "Server Error: Check your PHP code",
                    Toast.LENGTH_LONG
                ).show()
                resetLoginButton()
                return
            }

            val jsonResponse = JSONObject(response)
            val status = jsonResponse.optString("status", "error")
            val message = jsonResponse.optString("message", "")

            when (status.lowercase()) {
                "success" -> {
                    val userId = jsonResponse.optInt("user_id", -1)
                    val role = jsonResponse.optString("role", "")
                    val name = jsonResponse.optString("name", username)

                    if (userId == -1) {
                        Toast.makeText(
                            this@LoginActivity,
                            "Login error: Invalid user ID",
                            Toast.LENGTH_LONG
                        ).show()
                        resetLoginButton()
                        return
                    }

                    // ✅ FIXED: Create login session with user_id as primary key
                    sessionManager.createLoginSession(
                        userId = userId,
                        username = username,
                        name = name,
                        role = role
                    )

                    // Debug log to verify session
                    Log.d("LoginActivity", "Session created - UserID: $userId, Username: $username")

                    // Verify session was saved
                    val savedUserId = sessionManager.getUserId()
                    if (savedUserId == userId) {
                        Toast.makeText(
                            this@LoginActivity,
                            "Welcome $name ($role)",
                            Toast.LENGTH_SHORT
                        ).show()

                        val intent = Intent(this@LoginActivity, MainActivity::class.java)
                        startActivity(intent)
                        finish()
                    } else {
                        Toast.makeText(
                            this@LoginActivity,
                            "Session error: Failed to save login data",
                            Toast.LENGTH_LONG
                        ).show()
                        resetLoginButton()
                    }
                }
                "inactive" -> {
                    // Account not yet approved
                    jsonResponse.optInt("user_id", -1)
                    showPendingApprovalDialog()
                    resetLoginButton()
                }
                "error" -> {
                    // Show server error message or default message
                    val errorMsg = message.ifEmpty { "Login failed. Check username/password." }
                    Toast.makeText(this@LoginActivity, errorMsg, Toast.LENGTH_LONG).show()
                    resetLoginButton()
                }
                else -> {
                    Toast.makeText(
                        this@LoginActivity,
                        "Unexpected response: $status",
                        Toast.LENGTH_SHORT
                    ).show()
                    resetLoginButton()
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
            Toast.makeText(
                this@LoginActivity,
                "Error parsing response: ${e.localizedMessage}",
                Toast.LENGTH_LONG
            ).show()
            resetLoginButton()
        }
    }

    @SuppressLint("SetTextI18n")
    private fun resetLoginButton() {
        btnLogin?.isEnabled = true
        btnLogin?.text = "Login"
    }

    private fun showPendingApprovalDialog() {
        AlertDialog.Builder(this)
            .setTitle("Account Pending")
            .setMessage("Your account is waiting for Admin Approval.\nYou will receive a notification when active.")
            .setPositiveButton("OK") { dialog: DialogInterface?, _: Int ->
                // Clear password for better UX
                etPassword?.setText("")
            }
            .setCancelable(false)
            .show()
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == 101) {
            if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                // Notification permission granted
            } else {
                Toast.makeText(
                    this,
                    "Notification permission denied. You may not receive important updates.",
                    Toast.LENGTH_SHORT
                ).show()
            }
        }
    }
}
