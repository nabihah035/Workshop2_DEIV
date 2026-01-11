@file:Suppress("DEPRECATION")

package com.example.deiv.login

import android.annotation.SuppressLint
import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.example.deiv.MainActivity
import com.example.deiv.R

@SuppressLint("CustomSplashScreen")
class SplashActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Set splash screen theme colors
        window.statusBarColor = ContextCompat.getColor(this, R.color.primary_dark_blue)
        window.navigationBarColor = ContextCompat.getColor(this, R.color.primary_dark_blue)

        // Set the layout for splash screen
        setContentView(R.layout.activity_splash)

        // Delay for splash screen duration (2.5 seconds)
        Handler(Looper.getMainLooper()).postDelayed({
            checkLoginStatus()
        }, 2000) // 3 seconds
    }

    private fun checkLoginStatus() {
        val sessionManager = SessionManager(this)

        if (sessionManager.isLoggedIn()) {
            // User is logged in, go directly to MainActivity
            val intent = Intent(this, MainActivity::class.java)
            startActivity(intent)
        } else {
            // User is not logged in, go to WelcomeActivity
            val intent = Intent(this, WelcomeActivity::class.java)
            startActivity(intent)
        }

        finish() // Close the splash activity
    }
}