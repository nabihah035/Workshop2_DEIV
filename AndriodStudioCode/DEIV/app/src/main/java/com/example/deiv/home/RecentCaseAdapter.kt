package com.example.deiv.home

import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.example.deiv.R
import com.example.deiv.cases.CaseDetailActivity
import org.json.JSONArray
import org.json.JSONObject

class RecentCaseAdapter : RecyclerView.Adapter<RecentCaseAdapter.ViewHolder>() {

    private val list = ArrayList<JSONObject>()
    private var context: Context? = null

    @SuppressLint("NotifyDataSetChanged")
    fun setData(array: JSONArray) {
        list.clear()
        for (i in 0 until array.length()) {
            list.add(array.getJSONObject(i))
        }
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        context = parent.context
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_recent_case, parent, false)
        return ViewHolder(view)
    }

    @SuppressLint("SetTextI18n", "UseKtx")
    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        val obj = list[position]

        holder.tvCaseName.text = obj.getString("case_name")
        holder.tvCaseId.text = "CASE-${obj.getInt("Case_id")}"
        holder.tvDate.text = obj.getString("created_at")
        holder.tvStatus.text = obj.getString("status")

        // Set background color dynamically (keep rounded corners)
        val color = obj.getString("status_color")
        try {
            val bg = holder.tvStatus.background.mutate()
            bg.setTint(android.graphics.Color.parseColor(color))
        } catch (e: IllegalArgumentException) {
            e.printStackTrace()
        }

        // Make the item clickable - now starts Activity
        holder.itemView.setOnClickListener {
            val caseId = obj.getInt("Case_id")
            val intent = Intent(context, CaseDetailActivity::class.java)
            intent.putExtra("case_id", caseId)
            context?.startActivity(intent)
        }
    }

    override fun getItemCount() = list.size

    class ViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val tvCaseName: TextView = itemView.findViewById(R.id.tvCaseName)
        val tvCaseId: TextView = itemView.findViewById(R.id.tvCaseId)
        val tvDate: TextView = itemView.findViewById(R.id.tvDate)
        val tvStatus: TextView = itemView.findViewById(R.id.tvStatus)
    }
}