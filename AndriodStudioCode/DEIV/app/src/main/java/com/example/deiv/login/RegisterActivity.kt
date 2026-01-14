package com.example.deiv.login

import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.View
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.EditText
import android.widget.Spinner
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.google.firebase.firestore.FirebaseFirestore
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.concurrent.Executors

class RegisterActivity : AppCompatActivity() {
    // UI Elements
    private var etFirstName: EditText? = null
    private var etLastName: EditText? = null
    private var etUsername: EditText? = null
    private var etEmail: EditText? = null
    private var etPassword: EditText? = null
    private var etConfirmPassword: EditText? = null
    private var etOrganization: EditText? = null
    private var spinnerRole: Spinner? = null
    private var btnRegister: Button? = null

    // Firebase Instance
    private var fStore: FirebaseFirestore? = null

    // Role options
    private val roleOptions = arrayOf(
        "Digital Forensic Investigator",
        "Law Agencies",
        "Legal Professionals",
        "Institution"
    )

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)

        fStore = FirebaseFirestore.getInstance()

        // Initialize UI elements
        etFirstName = findViewById(R.id.etFirstName)
        etLastName = findViewById(R.id.etLastName)
        etUsername = findViewById(R.id.etRegUsername)
        etEmail = findViewById(R.id.etEmail)
        etPassword = findViewById(R.id.etRegPassword)
        etConfirmPassword = findViewById(R.id.etConfirmPassword)
        etOrganization = findViewById(R.id.etOrganization)
        spinnerRole = findViewById(R.id.spinnerRole)
        btnRegister = findViewById(R.id.btnRegister)

        // Setup role spinner
        setupRoleSpinner()

        // Setup login text click listener
        findViewById<android.widget.TextView>(R.id.tvLogin).setOnClickListener {
            startActivity(Intent(applicationContext, LoginActivity::class.java))
            finish()
        }

        btnRegister?.setOnClickListener {
            val firstName = etFirstName?.text?.toString()?.trim() ?: ""
            val lastName = etLastName?.text?.toString()?.trim() ?: ""
            val username = etUsername?.text?.toString()?.trim() ?: ""
            val email = etEmail?.text?.toString()?.trim() ?: ""
            val password = etPassword?.text?.toString()?.trim() ?: ""
            val confirmPassword = etConfirmPassword?.text?.toString()?.trim() ?: ""
            val organization = etOrganization?.text?.toString()?.trim() ?: ""
            val selectedRole = spinnerRole?.selectedItem?.toString() ?: ""

            if (firstName.isEmpty() || lastName.isEmpty() ||
                username.isEmpty() || email.isEmpty() ||
                password.isEmpty() || confirmPassword.isEmpty() ||
                organization.isEmpty() || selectedRole.isEmpty()) {
                Toast.makeText(this@RegisterActivity, "Please fill all fields", Toast.LENGTH_SHORT).show()
            } else if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
                Toast.makeText(this@RegisterActivity, "Please enter a valid email", Toast.LENGTH_SHORT).show()
            } else if (password != confirmPassword) {
                Toast.makeText(this@RegisterActivity, "Passwords do not match", Toast.LENGTH_SHORT).show()
            } else {
                registerToMySQL(firstName, lastName, username, email, password, organization, selectedRole)
            }
        }
    }

    private fun setupRoleSpinner() {
        val adapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_item,
            roleOptions
        )
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        spinnerRole?.adapter = adapter

        // Add listener to change organization hint based on role
        spinnerRole?.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                val selectedRole = roleOptions[position]
                if (selectedRole == "Institution") {
                    etOrganization?.hint = "Institution Name (e.g., UTeM)"
                } else {
                    etOrganization?.hint = "Organization (e.g., Bank Islam)"
                }
            }

            override fun onNothingSelected(parent: AdapterView<*>?) {
                // Do nothing
            }
        }
    }

    private fun registerToMySQL(
        fname: String,
        lname: String,
        username: String,
        email: String,
        password: String,
        organization: String,
        role: String
    ) {
        val executor = Executors.newSingleThreadExecutor()
        val handler = Handler(Looper.getMainLooper())

        executor.execute {
            try {
                val postData = StringBuilder()
                    .append("username=").append(URLEncoder.encode(username, "UTF-8"))
                    .append("&password=").append(URLEncoder.encode(password, "UTF-8"))
                    .append("&email=").append(URLEncoder.encode(email, "UTF-8"))
                    .append("&first_name=").append(URLEncoder.encode(fname, "UTF-8"))
                    .append("&last_name=").append(URLEncoder.encode(lname, "UTF-8"))
                    .append("&organization=").append(URLEncoder.encode(organization, "UTF-8"))
                    .append("&role=").append(URLEncoder.encode(role, "UTF-8"))
                    .append("&status=Pending")
                    .toString()

                val url = URL(ApiService.REGISTER)
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")
                conn.setRequestProperty("Charset", "UTF-8")

                conn.outputStream.use { os ->
                    os.write(postData.toByteArray())
                    os.flush()
                }

                val responseCode = conn.responseCode
                if (responseCode != HttpURLConnection.HTTP_OK) {
                    handler.post {
                        Toast.makeText(this@RegisterActivity, "Server Error: HTTP $responseCode", Toast.LENGTH_SHORT).show()
                    }
                    return@execute
                }

                val reader = BufferedReader(InputStreamReader(conn.inputStream))
                val response = StringBuilder()
                var line: String?
                while (reader.readLine().also { line = it } != null) {
                    response.append(line)
                }
                reader.close()

                handler.post {
                    handleRegisterResponse(response.toString(), username, email, role, organization)
                }
            } catch (e: Exception) {
                handler.post {
                    Toast.makeText(this@RegisterActivity, "Network Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    private fun handleRegisterResponse(
        response: String,
        username: String,
        email: String,
        role: String,
        organization: String
    ) {
        try {
            if (response.contains("<br") || response.contains("<!DOCTYPE") || response.contains("<html")) {
                Toast.makeText(this@RegisterActivity, "Server Error: Check your PHP code", Toast.LENGTH_LONG).show()
                return
            }

            val jsonResponse = JSONObject(response)
            val status = jsonResponse.optString("status", "error")
            val message = jsonResponse.optString("message", "")
            val userId = jsonResponse.optInt("user_id", 0)

            when (status.lowercase()) {
                "success" -> {
                    if (userId > 0) {
                        saveTokenToFirebase(username, email, role, organization, userId)
                    } else {
                        Toast.makeText(
                            this@RegisterActivity,
                            "Registration Successfully, Please wait for admin approval.",
                            Toast.LENGTH_LONG
                        ).show()
                        navigateToLogin()
                    }
                }
                "error" -> {
                    when {
                        message.contains("username", ignoreCase = true) -> {
                            Toast.makeText(this@RegisterActivity, "Username already exists", Toast.LENGTH_SHORT).show()
                        }
                        message.contains("email", ignoreCase = true) -> {
                            Toast.makeText(this@RegisterActivity, "Email already exists", Toast.LENGTH_SHORT).show()
                        }
                        else -> {
                            Toast.makeText(this@RegisterActivity, message.ifEmpty { "Registration failed" }, Toast.LENGTH_SHORT).show()
                        }
                    }
                }
                else -> {
                    Toast.makeText(this@RegisterActivity, "Unexpected response: $status", Toast.LENGTH_SHORT).show()
                }
            }
        } catch (e: Exception) {
            Toast.makeText(this@RegisterActivity, "Error parsing response: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
        }
    }

    private fun saveTokenToFirebase(
        username: String,
        email: String,
        role: String,
        organization: String,
        userId: Int
    ) {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (!task.isSuccessful) {
                Toast.makeText(this@RegisterActivity, "Failed to get FCM token: ${task.exception?.message}", Toast.LENGTH_SHORT).show()
                return@addOnCompleteListener
            }

            val token = task.result ?: ""

            val userData = hashMapOf(
                "user_id" to userId,
                "username" to username,
                "email" to email,
                "role" to role,
                "organization" to organization,
                "status" to "Pending",
                "fcmToken" to token,
                "registrationDate" to System.currentTimeMillis()
            )

            fStore?.collection("users")
                ?.document(email)
                ?.set(userData)
                ?.addOnSuccessListener {
                    Toast.makeText(this@RegisterActivity, "Registration Complete! Please wait for Admin Approval.", Toast.LENGTH_LONG).show()
                    navigateToLogin()
                }
                ?.addOnFailureListener { e ->
                    Toast.makeText(this@RegisterActivity, "Firestore Error: ${e.localizedMessage}", Toast.LENGTH_SHORT).show()
                    navigateToLogin()
                }
        }
    }

    private fun navigateToLogin() {
        clearForm()
        startActivity(Intent(applicationContext, LoginActivity::class.java))
        finish()
    }

    private fun clearForm() {
        etFirstName?.setText("")
        etLastName?.setText("")
        etUsername?.setText("")
        etEmail?.setText("")
        etPassword?.setText("")
        etConfirmPassword?.setText("")
        etOrganization?.setText("")
        spinnerRole?.setSelection(0)
    }
}
