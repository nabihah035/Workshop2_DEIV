package com.example.deiv.cases

import android.annotation.SuppressLint
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.example.deiv.R
import org.json.JSONArray
import org.json.JSONObject

class CaseAdapter(
    private val onClick: (Int) -> Unit
) : RecyclerView.Adapter<CaseAdapter.ViewHolder>() {

    private val list = ArrayList<JSONObject>()

    @SuppressLint("NotifyDataSetChanged")
    fun setData(array: JSONArray) {
        list.clear()
        for (i in 0 until array.length()) {
            list.add(array.getJSONObject(i))
        }
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_case, parent, false)
        return ViewHolder(view, onClick)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(list[position])
    }

    override fun getItemCount(): Int = list.size

    class ViewHolder(
        itemView: View,
        private val onClick: (Int) -> Unit
    ) : RecyclerView.ViewHolder(itemView) {

        private val tvCaseName: TextView = itemView.findViewById(R.id.tvCaseName)
        private val tvCaseId: TextView = itemView.findViewById(R.id.tvCaseId)
        private val tvDescription: TextView = itemView.findViewById(R.id.tvDescription)
        private val tvStatus: TextView = itemView.findViewById(R.id.tvStatus)
        private val tvDate: TextView = itemView.findViewById(R.id.tvDate)

        @SuppressLint("SetTextI18n", "UseKtx")
        fun bind(obj: JSONObject) {
            tvCaseName.text = obj.getString("case_name")
            tvCaseId.text = "CASE-${obj.getInt("Case_id")}"
            tvDescription.text = obj.getString("description")
            tvStatus.text = obj.getString("status")

            // APPLY STATUS COLOR FROM API
            if (obj.has("status_color")) {
                try {
                    val color = android.graphics.Color.parseColor(obj.getString("status_color"))
                    tvStatus.setBackgroundColor(color)
                } catch (e: IllegalArgumentException) {
                    e.printStackTrace()
                }
            }

            tvDate.text = obj.getString("created_at")

            itemView.setOnClickListener {
                onClick(obj.getInt("Case_id"))
            }
        }
    }
}
