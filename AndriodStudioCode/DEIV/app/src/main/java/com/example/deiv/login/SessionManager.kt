package com.example.deiv.login

import android.annotation.SuppressLint
import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit
import android.util.Log

class SessionManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("DEIV_SESSION", Context.MODE_PRIVATE)

    companion object {
        const val KEY_USER_ID = "user_id"
        const val KEY_IS_LOGGED_IN = "is_logged_in"
        const val KEY_USERNAME = "username"
        const val KEY_NAME = "name"
        const val KEY_ROLE = "role"
    }

    @SuppressLint("UseKtx")
    fun createLoginSession(userId: Int, username: String, name: String, role: String = "") {
        prefs.edit()
            .putBoolean(KEY_IS_LOGGED_IN, true)
            .putInt(KEY_USER_ID, userId)
            .putString(KEY_USERNAME, username)
            .putString(KEY_NAME, name)
            .putString(KEY_ROLE, role)
            .apply()
    }

    // Add this method to save user ID specifically
    @SuppressLint("UseKtx")
    fun setUserId(userId: Int) {
        prefs.edit()
            .putInt(KEY_USER_ID, userId)
            .apply()
    }

    fun isLoggedIn(): Boolean {
        return prefs.getBoolean(KEY_IS_LOGGED_IN, false)
    }

    fun getUserId(): Int {
        return prefs.getInt(KEY_USER_ID, -1).also {
            Log.d("SessionManager", "Retrieved user ID: $it")
        }
    }

    fun getName(): String {
        return prefs.getString(KEY_NAME, "") ?: ""
    }

    @SuppressLint("UseKtx")
    fun clearSessionData() {
        prefs.edit()
            .remove(KEY_USER_ID)
            .remove(KEY_NAME)
            .remove(KEY_USERNAME)
            .remove(KEY_ROLE)
            .apply()
    }

    fun logout() {
        clearSessionData()
        prefs.edit {
            putBoolean(KEY_IS_LOGGED_IN, false)
        }
    }
}