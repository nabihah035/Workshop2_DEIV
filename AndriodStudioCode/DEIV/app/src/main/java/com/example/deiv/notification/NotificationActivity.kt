package com.example.deiv.notification

import android.content.Intent
import android.os.Bundle
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
        val userId = sessionManager.getUserId()

        val url =
            "${ApiService.NOTIFICATION_LIST}?user_id=$userId&type=$type"

        val request = JsonObjectRequest(
            Request.Method.GET, url, null,
            { response ->
                notificationList.clear()
                val array = response.getJSONArray("notifications")

                for (i in 0 until array.length()) {
                    val obj = array.getJSONObject(i)

                    val notification = NotificationModel(
                        obj.getInt("Notification_id"),
                        obj.getString("message"),
                        obj.getString("status"),
                        obj.getString("date"),
                        obj.optInt("Evidence_id")
                    )
                    notificationList.add(notification)
                }
                adapter.notifyDataSetChanged()
            },
            {
                Toast.makeText(this, "Failed to load notifications", Toast.LENGTH_SHORT).show()
            }
        )

        Volley.newRequestQueue(this).add(request)
    }

    private fun markAsRead(notificationId: Int) {
        val request = object : StringRequest(
            Method.POST,
            ApiService.NOTIFICATION_MARK_READ,
            {
                loadNotifications(currentType)
            },
            {
                Toast.makeText(this, "Failed to update status", Toast.LENGTH_SHORT).show()
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
