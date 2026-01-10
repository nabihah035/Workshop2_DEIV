package com.example.deiv

import android.content.Intent
import android.os.Bundle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.setupWithNavController
import com.example.deiv.login.LoginActivity
import com.example.deiv.login.SessionManager
import com.google.android.material.bottomnavigation.BottomNavigationView
import android.widget.ImageButton
import android.widget.Toast
import android.util.Log

class MainActivity : AppCompatActivity() {

    private fun checkSession() {
        val sessionManager = SessionManager(this)
        if (!sessionManager.isLoggedIn()) {
            // Redirect to login if not logged in
            val intent = Intent(this, LoginActivity::class.java)
            startActivity(intent)
            finish()
        } else {
            // User is logged in, show welcome message
            val username = sessionManager.getUsername()
            Toast.makeText(this, "Welcome back, $username!", Toast.LENGTH_SHORT).show()

            // Debug log
            Log.d("MainActivity", "Session info: ${sessionManager.getSessionInfo()}")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Check session FIRST before setting content view
        checkSession()

        /* ================= EDGE TO EDGE ================= */
        enableEdgeToEdge()
        setContentView(R.layout.activity_main)

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, 0)
            insets
        }

        /* ================= NAV CONTROLLER ================= */
        val navHostFragment =
            supportFragmentManager.findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        val navController = navHostFragment.navController

        /* ================= BOTTOM NAV ================= */
        val bottomNav = findViewById<BottomNavigationView>(R.id.bottomNav)
        bottomNav.setupWithNavController(navController)

        /* ================= HEADER ICONS ================= */
        val btnNotification = findViewById<ImageButton>(R.id.btn_notification)
        val btnUser = findViewById<ImageButton>(R.id.btn_user)

        btnNotification.setOnClickListener {
            startActivity(
                Intent(this, com.example.deiv.notification.NotificationActivity::class.java)
            )
        }

        btnUser.setOnClickListener {
            navController.navigate(R.id.nav_user)
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        val navHostFragment =
            supportFragmentManager.findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        return navHostFragment.navController.navigateUp() || super.onSupportNavigateUp()
    }
}