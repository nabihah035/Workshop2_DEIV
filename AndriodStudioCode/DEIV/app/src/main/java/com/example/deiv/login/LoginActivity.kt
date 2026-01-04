package com.example.deiv.login

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.MainActivity
import com.example.deiv.R
import org.json.JSONObject

class LoginActivity : AppCompatActivity() {

    private val LOGIN_URL = "http://172.26.83.131/deiv_api/login.php"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        val session = SessionManager(this)

        // Already logged in? Go to MainActivity (HomeFragment)
        if (session.isLoggedIn()) {
            startActivity(Intent(this, MainActivity::class.java))
            finish()
            return
        }

        val btnLogin = findViewById<Button>(R.id.btnLogin)
        val etUsername = findViewById<EditText>(R.id.etUsername)
        val etPassword = findViewById<EditText>(R.id.etPassword)

        btnLogin.setOnClickListener {
            val username = etUsername.text.toString().trim()
            val password = etPassword.text.toString().trim()

            if (username.isEmpty() || password.isEmpty()) {
                Toast.makeText(this, "Enter username and password", Toast.LENGTH_SHORT).show()
            } else {
                loginUser(username, password, session)
            }
        }
    }

    private fun loginUser(username: String, password: String, session: SessionManager) {
        val queue = Volley.newRequestQueue(this)

        val postParams = hashMapOf(
            "username" to username,
            "password" to password
        )

        val stringRequest = object : StringRequest(
            Method.POST, LOGIN_URL,
            { response ->
                try {
                    val json = JSONObject(response)
                    if (json.getString("status") == "success") {
                        val userId = json.getInt("user_id")
                        session.createLoginSession(userId)

                        // Go to MainActivity (HomeFragment)
                        val intent = Intent(this, MainActivity::class.java)
                        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                        startActivity(intent)
                        finish()
                    } else {
                        Toast.makeText(this, json.getString("message"), Toast.LENGTH_SHORT).show()
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    Toast.makeText(this, "Invalid response from server", Toast.LENGTH_SHORT).show()
                }
            },
            { error ->
                Toast.makeText(this, "Network error: ${error.message}", Toast.LENGTH_SHORT).show()
            }
        ) {
            override fun getParams(): MutableMap<String, String> = postParams
        }

        queue.add(stringRequest)
    }
}
