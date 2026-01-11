package com.example.deiv.notification

import android.graphics.Color
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.example.deiv.R

class MyNotificationAdapter(
    private val notificationList: List<NotificationModel>,
    private val onItemClick: (NotificationModel) -> Unit
) : RecyclerView.Adapter<MyNotificationAdapter.NotificationViewHolder>() {

    class NotificationViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val tvMessage: TextView = itemView.findViewById(R.id.tv_message)
        val tvDate: TextView = itemView.findViewById(R.id.tv_date)
        val tvStatus: TextView = itemView.findViewById(R.id.tv_status)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): NotificationViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_notification, parent, false)
        return NotificationViewHolder(view)
    }

    override fun onBindViewHolder(holder: NotificationViewHolder, position: Int) {
        val notification = notificationList[position]

        holder.tvMessage.text = notification.message
        holder.tvDate.text = notification.date
        holder.tvStatus.text = notification.status

        // Change color based on status
        if (notification.status == "Unread") {
            holder.tvStatus.setTextColor(Color.RED)
        } else {
            holder.tvStatus.setTextColor(Color.GRAY)
        }

        holder.itemView.setOnClickListener {
            onItemClick(notification)
        }
    }

    override fun getItemCount(): Int {
        return notificationList.size
    }
}
