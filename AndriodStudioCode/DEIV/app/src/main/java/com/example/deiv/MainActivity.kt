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
import android.util.Log
import android.view.View

class MainActivity : AppCompatActivity() {

    private fun checkSession() {
        val sessionManager = SessionManager(this)
        if (!sessionManager.isLoggedIn()) {
            val intent = Intent(this, LoginActivity::class.java)
            startActivity(intent)
            finish()
        } else {
            Log.d("MainActivity", "Session info: ${sessionManager.getSessionInfo()}")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        checkSession()
        enableEdgeToEdge()
        setContentView(R.layout.activity_main)

        // FIX: Handle window insets properly for keyboard
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            val ime = insets.getInsets(WindowInsetsCompat.Type.ime())

            // Only apply padding if keyboard is NOT showing
            if (ime.bottom == 0) {
                v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            } else {
                // When keyboard is showing, don't add bottom padding
                v.setPadding(systemBars.left, systemBars.top, systemBars.right, 0)
            }
            insets
        }

        val navHostFragment =
            supportFragmentManager.findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        val navController = navHostFragment.navController

        val bottomNav = findViewById<BottomNavigationView>(R.id.bottomNav)
        bottomNav.setupWithNavController(navController)

        // FIX: Add window insets listener to bottom navigation
        ViewCompat.setOnApplyWindowInsetsListener(bottomNav) { v, insets ->
            val ime = insets.getInsets(WindowInsetsCompat.Type.ime())
            if (ime.bottom > 0) {
                // Hide bottom nav when keyboard is showing
                v.visibility = View.GONE
            } else {
                // Show bottom nav when keyboard is hidden
                v.visibility = View.VISIBLE
            }
            insets
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        val navHostFragment =
            supportFragmentManager.findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        return navHostFragment.navController.navigateUp() || super.onSupportNavigateUp()
    }
}