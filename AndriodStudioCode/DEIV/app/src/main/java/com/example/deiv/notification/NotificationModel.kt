package com.example.deiv.notification

data class NotificationModel(
    val Notification_id: Int,
    val message: String,
    val status: String,
    val date: String,
    val Evidence_id: Int?
)
