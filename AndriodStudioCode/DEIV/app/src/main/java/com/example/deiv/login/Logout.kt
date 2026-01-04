package com.example.deiv.login

import android.content.Context
import android.content.Intent
import com.android.volley.Request
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.login.LoginActivity

class Logout(private val context: Context) {

    private val LOGOUT_URL = "http://172.26.83.131/deiv_api/logout.php"

    fun logoutUser() {
        val session = SessionManager(context)
        val queue = Volley.newRequestQueue(context)

        val request = StringRequest(
            Request.Method.POST,
            LOGOUT_URL,
            {
                // Clear local session even if server fails
                session.logout()

                val intent = Intent(context, LoginActivity::class.java)
                intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                context.startActivity(intent)
            },
            {
                // If network error, still logout locally
                session.logout()

                val intent = Intent(context, LoginActivity::class.java)
                intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                context.startActivity(intent)
            }
        )

        queue.add(request)
    }
}
