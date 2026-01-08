package com.example.deiv.login

import android.content.Context
import android.content.Intent
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.api.ApiService
import android.widget.Toast

class Logout(private val context: Context) {

    fun logoutUser() {
        val session = SessionManager(context)
        val queue = Volley.newRequestQueue(context)

        val request = StringRequest(
            Request.Method.POST,
            ApiService.LOGOUT,
            { response ->
                // Server logout successful, clear local session
                session.logout()

                // Redirect to login screen
                navigateToLogin()
            },
            { error ->
                // If network error, still logout locally
                session.logout()

                // Redirect to login screen
                navigateToLogin()

                // Log the error for debugging
                error.printStackTrace()
            }
        )
        queue.add(request)
    }

    private fun navigateToLogin() {
        val intent = Intent(context, LoginActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        context.startActivity(intent)

        // Optional: Show a toast message
        Toast.makeText(context, "Logged out successfully", Toast.LENGTH_SHORT).show()
    }
}