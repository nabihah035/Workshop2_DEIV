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
        private val tvEvidenceCount: TextView = itemView.findViewById(R.id.tvEvidenceCount)
        private val tvVerifiedCount: TextView = itemView.findViewById(R.id.tvVerifiedCount)

        @SuppressLint("SetTextI18n")
        fun bind(obj: JSONObject) {
            tvCaseName.text = obj.getString("case_name")
            tvCaseId.text = "Case #${obj.getInt("Case_id")}"
            tvDescription.text = obj.getString("description")
            tvStatus.text = obj.getString("status")

            // Set evidence count
            val evidenceCount = if (obj.has("evidence_count")) obj.getInt("evidence_count") else 0
            tvEvidenceCount.text = evidenceCount.toString()

            // Set verified count (you'll need to add this to your API)
            val verifiedCount = if (obj.has("verified_count")) obj.getInt("verified_count") else 0
            tvVerifiedCount.text = verifiedCount.toString()

            // Apply status color
            // Apply status color with rounded background
            if (obj.has("status_color")) {
                try {
                    val color = android.graphics.Color.parseColor(obj.getString("status_color"))
                    val bg = tvStatus.background.mutate()
                    bg.setTint(color)
                } catch (e: IllegalArgumentException) {
                    e.printStackTrace()
                }
            }


            itemView.setOnClickListener {
                onClick(obj.getInt("Case_id"))
            }
        }
    }
}