package com.example.deiv.login

import android.content.Context
import android.content.SharedPreferences

class SessionManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("DEIV_SESSION", Context.MODE_PRIVATE)

    companion object {
        const val KEY_USER_ID = "user_id"
        const val KEY_IS_LOGGED_IN = "is_logged_in"
    }

    fun createLoginSession(userId: Int) {
        prefs.edit()
            .putBoolean(KEY_IS_LOGGED_IN, true)
            .putInt(KEY_USER_ID, userId)
            .apply()
    }

    fun isLoggedIn(): Boolean {
        return prefs.getBoolean(KEY_IS_LOGGED_IN, false)
    }

    fun getUserId(): Int {
        return prefs.getInt(KEY_USER_ID, -1)
    }

    fun logout() {
        prefs.edit().clear().apply()
    }
}
