package com.example.deiv.login

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import androidx.appcompat.app.AppCompatActivity

// --- CRITICAL FIX: Import your app's R file ---
import com.example.deiv.R

class WelcomeActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_welcome)

        // Clean Kotlin syntax (removed '?' to prevent null errors)
        val btnLogin = findViewById<Button>(R.id.btnGoToLogin)
        val btnRegister = findViewById<Button>(R.id.btnGoToRegister)

        // Click Login -> Go to Login Page
        btnLogin.setOnClickListener {
            val intent = Intent(this, LoginActivity::class.java)
            startActivity(intent)
        }

        // Click Register -> Go to Register Page
        btnRegister.setOnClickListener {
            val intent = Intent(this, RegisterActivity::class.java)
            startActivity(intent)
        }
    }
}