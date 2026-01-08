package com.example.deiv.login

import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.widget.Button
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.google.firebase.firestore.FirebaseFirestore
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.concurrent.Executors
import com.example.deiv.R
import com.example.deiv.api.ApiService

class RegisterActivity : AppCompatActivity() {
    // UI Elements
    private var etFirstName: EditText? = null
    private var etLastName: EditText? = null
    private var etUsername: EditText? = null
    private var etEmail: EditText? = null
    private var etPassword: EditText? = null
    private var btnRegister: Button? = null

    // Firebase Instance
    private var fStore: FirebaseFirestore? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)

        fStore = FirebaseFirestore.getInstance()

        etFirstName = findViewById(R.id.etFirstName)
        etLastName = findViewById(R.id.etLastName)
        etUsername = findViewById(R.id.etRegUsername)
        etEmail = findViewById(R.id.etEmail)
        etPassword = findViewById(R.id.etRegPassword)
        btnRegister = findViewById(R.id.btnRegister)

        btnRegister?.setOnClickListener {
            val firstName = etFirstName?.text?.toString()?.trim() ?: ""
            val lastName = etLastName?.text?.toString()?.trim() ?: ""
            val username = etUsername?.text?.toString()?.trim() ?: ""
            val email = etEmail?.text?.toString()?.trim() ?: ""
            val password = etPassword?.text?.toString()?.trim() ?: ""

            if (firstName.isEmpty() || lastName.isEmpty() ||
                username.isEmpty() || email.isEmpty() || password.isEmpty()) {
                Toast.makeText(this@RegisterActivity, "Please fill all fields", Toast.LENGTH_SHORT).show()
            } else if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
                // Optional: Add email validation
                Toast.makeText(this@RegisterActivity, "Please enter a valid email", Toast.LENGTH_SHORT).show()
            } else {
                registerToMySQL(firstName, lastName, username, email, password)
            }
        }
    }

    private fun registerToMySQL(
        fname: String,
        lname: String,
        username: String,
        email: String,
        password: String
    ) {
        val executor = Executors.newSingleThreadExecutor()
        val handler = Handler(Looper.getMainLooper())

        executor.execute {
            try {
                // 1. Prepare POST data
                val postData = StringBuilder()
                    .append("username=").append(URLEncoder.encode(username, "UTF-8"))
                    .append("&password=").append(URLEncoder.encode(password, "UTF-8"))
                    .append("&email=").append(URLEncoder.encode(email, "UTF-8"))
                    .append("&first_name=").append(URLEncoder.encode(fname, "UTF-8"))
                    .append("&last_name=").append(URLEncoder.encode(lname, "UTF-8"))
                    .append("&organization=").append(URLEncoder.encode("UTEM", "UTF-8"))
                    .append("&role=user")
                    .toString()

                // 2. Connect using ApiService.REGISTER
                val url = URL(ApiService.REGISTER)
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")
                conn.setRequestProperty("Charset", "UTF-8")

                // 3. Write data
                conn.outputStream.use { os ->
                    os.write(postData.toByteArray())
                    os.flush()
                }

                // 4. Check response code
                val responseCode = conn.responseCode
                if (responseCode != HttpURLConnection.HTTP_OK) {
                    handler.post {
                        Toast.makeText(
                            this@RegisterActivity,
                            "Server Error: HTTP $responseCode",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                    return@execute
                }

                // 5. Read response
                val reader = BufferedReader(InputStreamReader(conn.inputStream))
                val response = StringBuilder()
                var line: String?
                while (reader.readLine().also { line = it } != null) {
                    response.append(line)
                }
                reader.close()

                // 6. Handle response on main thread
                handler.post {
                    handleRegisterResponse(response.toString(), username, email)
                }
            } catch (e: Exception) {
                handler.post {
                    Toast.makeText(
                        this@RegisterActivity,
                        "Network Error: ${e.localizedMessage}",
                        Toast.LENGTH_SHORT
                    ).show()
                }
            }
        }
    }

    private fun handleRegisterResponse(response: String, username: String, email: String) {
        try {
            // Check for PHP/HTML errors
            if (response.contains("<br") || response.contains("<!DOCTYPE") || response.contains("<html")) {
                Toast.makeText(
                    this@RegisterActivity,
                    "Server Error: Check your PHP code",
                    Toast.LENGTH_LONG
                ).show()
                return
            }

            val jsonResponse = JSONObject(response)
            val status = jsonResponse.optString("status", "error")
            val message = jsonResponse.optString("message", "")

            when (status.lowercase()) {
                "success" -> {
                    // MySQL registration successful, now save to Firebase
                    saveTokenToFirebase(username, email)
                }
                "error" -> {
                    // Handle specific error messages
                    when {
                        message.contains("username", ignoreCase = true) -> {
                            Toast.makeText(
                                this@RegisterActivity,
                                "Username already exists",
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                        message.contains("email", ignoreCase = true) -> {
                            Toast.makeText(
                                this@RegisterActivity,
                                "Email already exists",
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                        else -> {
                            Toast.makeText(
                                this@RegisterActivity,
                                message.ifEmpty { "Registration failed" },
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                    }
                }
                else -> {
                    Toast.makeText(
                        this@RegisterActivity,
                        "Unexpected response: $status",
                        Toast.LENGTH_SHORT
                    ).show()
                }
            }
        } catch (e: Exception) {
            Toast.makeText(
                this@RegisterActivity,
                "Error parsing response: ${e.localizedMessage}",
                Toast.LENGTH_SHORT
            ).show()
        }
    }

    private fun saveTokenToFirebase(username: String, email: String) {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (!task.isSuccessful) {
                Toast.makeText(
                    this@RegisterActivity,
                    "Failed to get FCM token: ${task.exception?.message}",
                    Toast.LENGTH_SHORT
                ).show()
                return@addOnCompleteListener
            }

            val token = task.result ?: ""

            val userData = hashMapOf(
                "username" to username,
                "email" to email,
                "status" to "inactive",
                "fcmToken" to token,
                "registrationDate" to System.currentTimeMillis()
            )

            fStore?.collection("users")
                ?.document(email)
                ?.set(userData)
                ?.addOnSuccessListener {
                    Toast.makeText(
                        this@RegisterActivity,
                        "Registration Complete! Please wait for Admin Approval.",
                        Toast.LENGTH_LONG
                    ).show()

                    // Clear form and navigate to login
                    clearForm()
                    startActivity(Intent(applicationContext, LoginActivity::class.java))
                    finish()
                }
                ?.addOnFailureListener { e ->
                    Toast.makeText(
                        this@RegisterActivity,
                        "Firestore Error: ${e.localizedMessage}",
                        Toast.LENGTH_SHORT
                    ).show()
                }
        }
    }

    private fun clearForm() {
        etFirstName?.setText("")
        etLastName?.setText("")
        etUsername?.setText("")
        etEmail?.setText("")
        etPassword?.setText("")
    }
}