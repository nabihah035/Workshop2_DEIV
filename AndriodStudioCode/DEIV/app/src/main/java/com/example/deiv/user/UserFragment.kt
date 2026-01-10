package com.example.deiv.user

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.*
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.example.deiv.login.Logout
import com.example.deiv.login.SessionManager
import com.example.deiv.model.UserProfile
import okhttp3.*
import org.json.JSONObject
import java.io.IOException

class UserFragment : Fragment() {

    private lateinit var tvFullName: TextView
    private lateinit var tvUsername: TextView
    private lateinit var tvUserStatus: TextView
    private lateinit var tvOrganization: TextView
    private lateinit var tvCreatedAt: TextView
    private lateinit var tvFullNameValue: TextView
    private lateinit var tvUsernameValue: TextView
    private lateinit var progressBar: ProgressBar
    private lateinit var btnEditProfile: Button
    private lateinit var btnChangePassword: Button
    private lateinit var btnLogout: Button
    private lateinit var sessionManager: SessionManager
    private lateinit var currentUser: UserProfile

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        val view = inflater.inflate(R.layout.fragment_user, container, false)

        // Initialize views
        tvFullName = view.findViewById(R.id.tvFullName)
        tvUsername = view.findViewById(R.id.tvUsername)
        tvUserStatus = view.findViewById(R.id.tvUserStatus)
        tvOrganization = view.findViewById(R.id.tvOrganization)
        tvCreatedAt = view.findViewById(R.id.tvCreatedAt)
        tvFullNameValue = view.findViewById(R.id.tvFullNameValue)
        tvUsernameValue = view.findViewById(R.id.tvUsernameValue)
        progressBar = view.findViewById(R.id.progressBar)
        btnEditProfile = view.findViewById(R.id.btnEditProfile)
        btnChangePassword = view.findViewById(R.id.btnChangePassword)
        btnLogout = view.findViewById(R.id.btnLogout)

        // Initialize SessionManager
        sessionManager = SessionManager(requireContext())

        // Check login status
        if (!sessionManager.isLoggedIn()) {
            // User not logged in - show login prompt
            showLoginPrompt()
            disableButtons()
        } else {
            // User is logged in, load profile data
            loadUserProfile()
        }

        btnEditProfile.setOnClickListener {
            if (this::currentUser.isInitialized) {
                showEditProfileDialog()
            } else {
                Toast.makeText(requireContext(), "Please wait for user data to load", Toast.LENGTH_SHORT).show()
            }
        }

        btnChangePassword.setOnClickListener {
            if (sessionManager.isLoggedIn()) {
                showChangePasswordDialog()
            } else {
                Toast.makeText(requireContext(), "Please login first", Toast.LENGTH_SHORT).show()
            }
        }

        btnLogout.setOnClickListener {
            if (sessionManager.isLoggedIn()) {
                Logout(requireContext()).logoutUser()
            } else {
                // If not logged in, just navigate to login
                Toast.makeText(requireContext(), "Please login", Toast.LENGTH_SHORT).show()
            }
        }

        return view
    }

    private fun disableButtons() {
        btnEditProfile.isEnabled = false
        btnChangePassword.isEnabled = false
    }

    private fun showLoginPrompt() {
        tvFullName.text = "Guest User"
        tvUsername.text = "@guest"
        tvFullNameValue.text = "Guest User"
        tvUsernameValue.text = "guest"
        tvUserStatus.text = "Not Logged In"
        tvUserStatus.setTextColor(ContextCompat.getColor(requireContext(), R.color.red))
        tvOrganization.text = "Please login to view profile"
        tvCreatedAt.text = "-"

        Toast.makeText(requireContext(), "Please login to access profile features", Toast.LENGTH_LONG).show()
    }

    private fun loadUserProfile() {
        progressBar.visibility = View.VISIBLE

        val userId = sessionManager.getUserId()

        if (userId <= 0) {
            progressBar.visibility = View.GONE
            Toast.makeText(requireContext(), "User not logged in. Please login again.", Toast.LENGTH_SHORT).show()
            disableButtons()
            return
        }

        Log.d("UserFragment", "Loading user profile for user ID: $userId")

        val url = "${ApiService.USER_PROFILE}?user_id=$userId"
        val client = OkHttpClient()
        val request = Request.Builder()
            .url(url)
            .get()
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onResponse(call: Call, response: Response) {
                val responseBody = response.body?.string()

                activity?.runOnUiThread {
                    progressBar.visibility = View.GONE

                    if (response.isSuccessful && responseBody != null) {
                        try {
                            val jsonObject = JSONObject(responseBody)
                            val status = jsonObject.getString("status")

                            if (status == "success") {
                                val data = jsonObject.getJSONObject("data")

                                currentUser = UserProfile(
                                    user_id = data.getInt("user_id"),
                                    username = data.getString("username"),
                                    email = data.getString("email"),
                                    full_name = data.getString("full_name"),
                                    first_name = data.getString("first_name"),
                                    last_name = data.getString("last_name"),
                                    role = data.getString("role"),
                                    status = data.getString("status"),
                                    organization = data.getString("organization"),
                                    created_at = data.getString("created_at")
                                )

                                displayUserData(currentUser)
                                btnEditProfile.isEnabled = true
                                btnChangePassword.isEnabled = true
                            } else {
                                val message = jsonObject.getString("message")
                                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
                                disableButtons()
                            }
                        } catch (e: Exception) {
                            Log.e("UserFragment", "JSON Parsing Error: ${e.message}")
                            Toast.makeText(requireContext(), "Error loading profile", Toast.LENGTH_SHORT).show()
                            disableButtons()
                        }
                    } else {
                        Toast.makeText(requireContext(), "Server error: ${response.code}", Toast.LENGTH_SHORT).show()
                        disableButtons()
                    }
                }
            }

            override fun onFailure(call: Call, e: IOException) {
                activity?.runOnUiThread {
                    progressBar.visibility = View.GONE
                    Toast.makeText(requireContext(), "Network error: ${e.message}", Toast.LENGTH_SHORT).show()
                    disableButtons()
                }
            }
        })
    }

    @SuppressLint("SetTextI18n")
    private fun displayUserData(user: UserProfile) {
        tvFullName.text = user.full_name
        tvUsername.text = "@${user.username}"
        tvFullNameValue.text = user.full_name
        tvUsernameValue.text = user.username
        tvUserStatus.text = user.status

        if (user.status.equals("Active", ignoreCase = true)) {
            tvUserStatus.setTextColor(ContextCompat.getColor(requireContext(), R.color.green))
        } else {
            tvUserStatus.setTextColor(ContextCompat.getColor(requireContext(), R.color.red))
        }

        tvOrganization.text = user.organization
        tvCreatedAt.text = user.created_at
    }

    private fun showEditProfileDialog() {
        if (!this::currentUser.isInitialized) {
            Toast.makeText(requireContext(), "User data not loaded", Toast.LENGTH_SHORT).show()
            return
        }

        val dialogView = layoutInflater.inflate(R.layout.dialog_edit_profile, null)

        val etFirstName = dialogView.findViewById<EditText>(R.id.etFirstName)
        val etLastName = dialogView.findViewById<EditText>(R.id.etLastName)
        val etUsername = dialogView.findViewById<EditText>(R.id.etUsername)
        val btnSave = dialogView.findViewById<Button>(R.id.btnSave)
        val btnCancel = dialogView.findViewById<Button>(R.id.btnCancel)

        etFirstName.setText(currentUser.first_name)
        etLastName.setText(currentUser.last_name)
        etUsername.setText(currentUser.username)

        val dialog = AlertDialog.Builder(requireContext())
            .setView(dialogView)
            .create()

        btnSave.setOnClickListener {
            val firstName = etFirstName.text.toString().trim()
            val lastName = etLastName.text.toString().trim()
            val username = etUsername.text.toString().trim()

            if (validateEditProfileInput(firstName, lastName, username)) {
                updateUserProfile(firstName, lastName, username)
                dialog.dismiss()
            }
        }

        btnCancel.setOnClickListener {
            dialog.dismiss()
        }

        dialog.show()
    }

    private fun validateEditProfileInput(firstName: String, lastName: String, username: String): Boolean {
        if (firstName.isEmpty()) {
            Toast.makeText(requireContext(), "First name is required", Toast.LENGTH_SHORT).show()
            return false
        }
        if (username.isEmpty()) {
            Toast.makeText(requireContext(), "Username is required", Toast.LENGTH_SHORT).show()
            return false
        }
        return true
    }

    private fun updateUserProfile(firstName: String, lastName: String, username: String) {
        progressBar.visibility = View.VISIBLE

        // Get user ID from session
        val userId = sessionManager.getUserId()

        // Create form data for POST request - ADD USER_ID
        val formBody = FormBody.Builder()
            .add("user_id", userId.toString())  // Add user_id parameter
            .add("first_name", firstName)
            .add("last_name", lastName)
            .add("username", username)
            .build()

        val client = OkHttpClient()
        val request = Request.Builder()
            .url(ApiService.UPDATE_PROFILE)
            .post(formBody)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                activity?.runOnUiThread {
                    progressBar.visibility = View.GONE
                    Toast.makeText(requireContext(), "Network error: ${e.message}", Toast.LENGTH_SHORT).show()
                }
            }

            override fun onResponse(call: Call, response: Response) {
                val responseBody = response.body?.string()

                activity?.runOnUiThread {
                    progressBar.visibility = View.GONE

                    if (response.isSuccessful && responseBody != null) {
                        try {
                            val jsonResponse = JSONObject(responseBody)
                            val status = jsonResponse.getString("status")
                            val message = jsonResponse.getString("message")

                            if (status == "success") {
                                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()

                                // Update current user object
                                currentUser.first_name = firstName
                                currentUser.last_name = lastName
                                currentUser.username = username
                                currentUser.full_name = "$firstName $lastName"

                                // IMPORTANT: Update session with new username
                                sessionManager.createLoginSession(
                                    userId = userId,
                                    username = username,
                                    name = currentUser.full_name,
                                    role = currentUser.role
                                )

                                // Update display
                                displayUserData(currentUser)

                                Log.d("UserFragment", "Session updated after profile edit: ${sessionManager.getSessionInfo()}")
                            } else {
                                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
                            }
                        } catch (_: Exception) {
                            Toast.makeText(requireContext(), "Error parsing response", Toast.LENGTH_SHORT).show()
                        }
                    } else {
                        Toast.makeText(requireContext(), "Failed to update profile: ${response.code}", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        })
    }

    private fun showChangePasswordDialog() {
        val dialogView = layoutInflater.inflate(R.layout.dialog_change_password, null)

        val etCurrentPassword = dialogView.findViewById<EditText>(R.id.etCurrentPassword)
        val etNewPassword = dialogView.findViewById<EditText>(R.id.etNewPassword)
        val etConfirmPassword = dialogView.findViewById<EditText>(R.id.etConfirmPassword)
        val btnSavePassword = dialogView.findViewById<Button>(R.id.btnSavePassword)
        val btnCancelPassword = dialogView.findViewById<Button>(R.id.btnCancelPassword)

        val dialog = AlertDialog.Builder(requireContext())
            .setView(dialogView)
            .create()

        btnSavePassword.setOnClickListener {
            val currentPassword = etCurrentPassword.text.toString().trim()
            val newPassword = etNewPassword.text.toString().trim()
            val confirmPassword = etConfirmPassword.text.toString().trim()

            if (validatePasswordInput(currentPassword, newPassword, confirmPassword)) {
                changePassword(currentPassword, newPassword)
                dialog.dismiss()
            }
        }

        btnCancelPassword.setOnClickListener {
            dialog.dismiss()
        }

        dialog.show()
    }

    private fun validatePasswordInput(currentPassword: String, newPassword: String, confirmPassword: String): Boolean {
        if (currentPassword.isEmpty()) {
            Toast.makeText(requireContext(), "Current password is required", Toast.LENGTH_SHORT).show()
            return false
        }
        if (newPassword.isEmpty()) {
            Toast.makeText(requireContext(), "New password is required", Toast.LENGTH_SHORT).show()
            return false
        }
        if (newPassword.length < 6) {
            Toast.makeText(requireContext(), "New password must be at least 6 characters", Toast.LENGTH_SHORT).show()
            return false
        }
        if (newPassword != confirmPassword) {
            Toast.makeText(requireContext(), "Passwords do not match", Toast.LENGTH_SHORT).show()
            return false
        }
        if (currentPassword == newPassword) {
            Toast.makeText(requireContext(), "New password must be different from current password", Toast.LENGTH_SHORT).show()
            return false
        }
        return true
    }

    private fun changePassword(currentPassword: String, newPassword: String) {
        progressBar.visibility = View.VISIBLE

        // Get user ID from session
        val userId = sessionManager.getUserId()

        // Create form data for POST request - ADD USER_ID
        val formBody = FormBody.Builder()
            .add("user_id", userId.toString())  // Add user_id parameter
            .add("current_password", currentPassword)
            .add("new_password", newPassword)
            .build()

        val client = OkHttpClient()
        val request = Request.Builder()
            .url(ApiService.CHANGE_PASSWORD)
            .post(formBody)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                activity?.runOnUiThread {
                    progressBar.visibility = View.GONE
                    Toast.makeText(requireContext(), "Network error: ${e.message}", Toast.LENGTH_SHORT).show()
                }
            }

            override fun onResponse(call: Call, response: Response) {
                val responseBody = response.body?.string()

                activity?.runOnUiThread {
                    progressBar.visibility = View.GONE

                    if (response.isSuccessful && responseBody != null) {
                        try {
                            val jsonResponse = JSONObject(responseBody)
                            val status = jsonResponse.getString("status")
                            val message = jsonResponse.getString("message")

                            if (status == "success") {
                                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
                            } else {
                                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
                            }
                        } catch (_: Exception) {
                            Toast.makeText(requireContext(), "Error parsing response", Toast.LENGTH_SHORT).show()
                        }
                    } else {
                        Toast.makeText(requireContext(), "Failed to change password: ${response.code}", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        })
    }

    override fun onResume() {
        super.onResume()
        // Refresh data when fragment resumes
        if (sessionManager.isLoggedIn()) {
            loadUserProfile()
        }
    }
}