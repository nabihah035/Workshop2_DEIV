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
        Log.d("SessionManager", "Creating login session: UserID=$userId, Username=$username, Name=$name")

        prefs.edit()
            .putBoolean(KEY_IS_LOGGED_IN, true)
            .putInt(KEY_USER_ID, userId)
            .putString(KEY_USERNAME, username)
            .putString(KEY_NAME, name)
            .putString(KEY_ROLE, role)
            .apply()

        // Verify the session was saved
        Log.d("SessionManager", "Session saved - isLoggedIn: ${isLoggedIn()}, UserID: ${getUserId()}")
    }

    // Add this method to save user ID specifically
    @SuppressLint("UseKtx")
    fun setUserId(userId: Int) {
        prefs.edit()
            .putInt(KEY_USER_ID, userId)
            .apply()
        Log.d("SessionManager", "User ID set to: $userId")
    }

    fun isLoggedIn(): Boolean {
        val loggedIn = prefs.getBoolean(KEY_IS_LOGGED_IN, false)
        Log.d("SessionManager", "isLoggedIn() returning: $loggedIn")
        return loggedIn
    }

    fun getUserId(): Int {
        val userId = prefs.getInt(KEY_USER_ID, -1)
        Log.d("SessionManager", "getUserId() returning: $userId")
        return userId
    }

    fun getUsername(): String {
        return prefs.getString(KEY_USERNAME, "") ?: ""
    }

    fun getName(): String {
        return prefs.getString(KEY_NAME, "") ?: ""
    }

    fun getRole(): String {
        return prefs.getString(KEY_ROLE, "") ?: ""
    }

    fun getSessionInfo(): String {
        return "UserID: ${getUserId()}, Username: ${getUsername()}, Name: ${getName()}, Role: ${getRole()}"
    }

    @SuppressLint("UseKtx")
    fun clearSessionData() {
        Log.d("SessionManager", "Clearing session data")
        prefs.edit()
            .remove(KEY_USER_ID)
            .remove(KEY_NAME)
            .remove(KEY_USERNAME)
            .remove(KEY_ROLE)
            .apply()
    }

    fun logout() {
        Log.d("SessionManager", "Logging out user")
        clearSessionData()
        prefs.edit {
            putBoolean(KEY_IS_LOGGED_IN, false)
        }
        Log.d("SessionManager", "After logout - isLoggedIn: ${isLoggedIn()}")
    }
}