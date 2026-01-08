package com.example.deiv.model

data class UserProfile(
    var user_id: Int = 0,
    var username: String = "",
    var email: String = "",
    var full_name: String = "",
    var first_name: String = "",
    var last_name: String = "",
    var role: String = "",
    var status: String = "",
    var organization: String = "",
    var created_at: String = ""
)