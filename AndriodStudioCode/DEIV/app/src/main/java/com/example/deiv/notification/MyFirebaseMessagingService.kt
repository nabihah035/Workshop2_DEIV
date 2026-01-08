package com.example.deiv.notification

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.RingtoneManager
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.firestore.FirebaseFirestore
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.example.deiv.R
import com.example.deiv.MainActivity

class MyFirebaseMessagingService : FirebaseMessagingService() {

    // 1. This method runs when the notification arrives
    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        // If the message has a notification payload, show it
        remoteMessage.notification?.let {
            sendNotification(it.title, it.body)
        }
    }

    // 2. [NEW] This method runs if Firebase changes the token (Security feature)
    // This ensures your database always has the correct token.
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d("FCM", "New token generated: $token")
        sendRegistrationToServer(token)
    }

    private fun sendRegistrationToServer(token: String) {
        // If the user is already logged in, update the token in Firestore
        val currentUser = FirebaseAuth.getInstance().currentUser
        if (currentUser != null && currentUser.email != null) {
            FirebaseFirestore.getInstance().collection("users")
                .document(currentUser.email!!)
                .update("fcmToken", token)
                .addOnSuccessListener { Log.d("FCM", "Token updated in DB") }
                .addOnFailureListener { e -> Log.e("FCM", "Failed to update token", e) }
        }
    }

    private fun sendNotification(title: String?, messageBody: String?) {
        val intent = Intent(this, MainActivity::class.java)
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)

        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_ONE_SHOT or PendingIntent.FLAG_IMMUTABLE
        )

        val channelId = "admin_approval_channel"
        val defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

        val notificationBuilder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title ?: getString(R.string.app_name))
            .setContentText(messageBody)
            .setAutoCancel(true)
            .setSound(defaultSoundUri)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .setContentIntent(pendingIntent)

        val notificationManager =
            getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Since Android Oreo (API 26), a channel is required
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "Admin Approval Notifications",
                NotificationManager.IMPORTANCE_HIGH
            )
            channel.setDescription("Notifications for account approval")
            notificationManager.createNotificationChannel(channel)
        }

        notificationManager.notify(0, notificationBuilder.build())
    }
}