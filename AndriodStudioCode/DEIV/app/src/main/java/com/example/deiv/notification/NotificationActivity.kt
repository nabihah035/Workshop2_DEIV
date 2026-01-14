package com.example.deiv.notification

import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.widget.Button
import android.widget.ImageButton
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.android.volley.Request
import com.android.volley.toolbox.JsonObjectRequest
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.deiv.MainActivity
import com.example.deiv.R
import com.example.deiv.api.ApiService
import com.example.deiv.login.SessionManager
import org.json.JSONException
import org.json.JSONObject
import com.example.deiv.login.LoginActivity

class NotificationActivity : AppCompatActivity() {

    private lateinit var recyclerView: RecyclerView
    private lateinit var adapter: MyNotificationAdapter
    private val notificationList = ArrayList<NotificationModel>()
    private lateinit var sessionManager: SessionManager

    private var currentType = "unread" // default

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_notification)

        sessionManager = SessionManager(this)

        if (!sessionManager.isLoggedIn()) {
            startActivity(Intent(this, LoginActivity::class.java))
            finish()
            return
        }

        // Back button
        val btnBack: ImageButton = findViewById(R.id.btn_back)
        btnBack.setOnClickListener {
            val intent = Intent(this, MainActivity::class.java)
            intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            startActivity(intent)
            finish()
        }

        // Buttons
        val btnUnread: Button = findViewById(R.id.btn_unread)
        val btnRead: Button = findViewById(R.id.btn_read)

        // RecyclerView
        recyclerView = findViewById(R.id.recycler_notification)
        recyclerView.layoutManager = LinearLayoutManager(this)

        adapter = MyNotificationAdapter(notificationList) { notification ->
            if (notification.status == "Unread") {
                markAsRead(notification.Notification_id)
            }
        }

        recyclerView.adapter = adapter

        // Button actions
        btnUnread.setOnClickListener {
            currentType = "unread"
            loadNotifications("unread")
        }

        btnRead.setOnClickListener {
            currentType = "read"
            loadNotifications("read")
        }

        // Load unread notifications by default
        loadNotifications("unread")
    }

    private fun loadNotifications(type: String) {
        if (!sessionManager.isLoggedIn()) {
            Toast.makeText(this, "Please login first", Toast.LENGTH_SHORT).show()
            startActivity(Intent(this, LoginActivity::class.java))
            finish()
            return
        }

        val userId = sessionManager.getUserId()

        if (userId <= 0) {
            Toast.makeText(this, "Session error. Please login again.", Toast.LENGTH_SHORT).show()
            sessionManager.logout()
            startActivity(Intent(this, LoginActivity::class.java))
            finish()
            return
        }

        Log.d("NotificationActivity", "Loading notifications for user: $userId")

        val url = "${ApiService.NOTIFICATION_LIST}?user_id=$userId&type=$type"
        val request = JsonObjectRequest(
            Request.Method.GET, url, null,
            { response ->
                try {
                    Log.d("NotificationActivity", "Response: ${response.toString()}")
                    notificationList.clear()

                    val success = response.optBoolean("success", false)
                    if (!success) {
                        val message = response.optString("message", "Unknown error")
                        Toast.makeText(this, "Error: $message", Toast.LENGTH_SHORT).show()
                        return@JsonObjectRequest
                    }

                    if (response.has("notifications")) {
                        val array = response.getJSONArray("notifications")
                        Log.d("NotificationActivity", "Found ${array.length()} notifications")

                        for (i in 0 until array.length()) {
                            val obj = array.getJSONObject(i)
                            Log.d("NotificationActivity", "Notification $i: $obj")

                            val notification = NotificationModel(
                                obj.getInt("Notification_id"),
                                obj.getString("message"),
                                obj.getString("status"),
                                obj.getString("date"),
                                if (obj.isNull("Evidence_id")) null else obj.getInt("Evidence_id"),
                                if (obj.isNull("Case_id")) null else obj.getInt("Case_id")
                            )

                            notificationList.add(notification)
                        }
                    } else {
                        Log.d("NotificationActivity", "No notifications found in response")
                        Toast.makeText(this, "No notifications found", Toast.LENGTH_SHORT).show()
                    }
                    adapter.notifyDataSetChanged()
                } catch (e: JSONException) {
                    e.printStackTrace()
                    Log.e("NotificationActivity", "JSON Error: ${e.message}")
                    Toast.makeText(this, "Data Error: ${e.message}", Toast.LENGTH_SHORT).show()
                }
            },
            { error ->
                error.printStackTrace()
                Log.e("NotificationActivity", "Volley Error: ${error.message}")
                Toast.makeText(this, "Failed to load notifications: ${error.message}", Toast.LENGTH_SHORT).show()
            }
        )

        Volley.newRequestQueue(this).add(request)
    }

    private fun markAsRead(notificationId: Int) {
        Log.d("NotificationActivity", "Marking as read: $notificationId")

        val request = object : StringRequest(
            Method.POST,
            ApiService.NOTIFICATION_MARK_READ,
            { response ->
                Log.d("NotificationActivity", "Mark read response: $response")
                try {
                    val jsonResponse = JSONObject(response)
                    if (jsonResponse.getBoolean("success")) {
                        loadNotifications(currentType)
                    } else {
                        Toast.makeText(this, "Failed: ${jsonResponse.getString("message")}", Toast.LENGTH_SHORT).show()
                    }
                } catch (_: Exception) {
                    Toast.makeText(this, "Response error", Toast.LENGTH_SHORT).show()
                }
            },
            { error ->
                error.printStackTrace()
                Toast.makeText(this, "Failed to update status: ${error.message}", Toast.LENGTH_SHORT).show()
            }
        ) {
            override fun getParams(): MutableMap<String, String> {
                val params = HashMap<String, String>()
                params["notification_id"] = notificationId.toString()
                return params
            }
        }

        Volley.newRequestQueue(this).add(request)
    }
}
