package com.example.deiv.cases

import android.annotation.SuppressLint
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import org.json.JSONObject
import org.json.JSONArray
import com.example.deiv.R
import android.graphics.Color

class EvidenceAdapter : RecyclerView.Adapter<EvidenceAdapter.ViewHolder>() {

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
            .inflate(R.layout.item_evidence, parent, false)
        return ViewHolder(view)
    }

    @SuppressLint("SetTextI18n")
    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        val obj = list[position]

        holder.tvFileName.text = obj.getString("file_name")
        holder.tvEvidenceId.text = "Evidence #${obj.getInt("Evidence_id")}"
        holder.tvDate.text = obj.getString("upload_date")

        // Set status indicator
        val status = obj.getString("status")
        holder.tvStatus.text = status

        when (status.lowercase()) {
            "verified" -> {
                holder.tvStatus.setBackgroundResource(R.drawable.badge_verified)
                holder.ivVerified.setImageResource(R.drawable.ic_verified)
                holder.ivVerified.visibility = View.VISIBLE
            }
            "tampered" -> {
                holder.tvStatus.setBackgroundResource(R.drawable.badge_tampered)
                holder.ivVerified.setImageResource(R.drawable.ic_tampered)
                holder.ivVerified.visibility = View.VISIBLE
            }
            else -> {
                holder.tvStatus.setBackgroundResource(R.drawable.badge_pending)
                holder.ivVerified.setImageResource(R.drawable.ic_pending)
                holder.ivVerified.visibility = View.VISIBLE
            }
        }

        // Handle metadata - IMPORTANT: Your PHP returns metadata as part of evidence object
        holder.bindMetadata(obj)
    }

    override fun getItemCount() = list.size

    class ViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val tvFileName: TextView = itemView.findViewById(R.id.tvFileName)
        val tvEvidenceId: TextView = itemView.findViewById(R.id.tvEvidenceId)
        val tvStatus: TextView = itemView.findViewById(R.id.tvStatus)
        val tvDate: TextView = itemView.findViewById(R.id.tvDate)
        val ivVerified: ImageView = itemView.findViewById(R.id.ivVerified)
        val metadataContainer: LinearLayout = itemView.findViewById(R.id.metadataContainer)
        val metadataItems: LinearLayout = itemView.findViewById(R.id.metadataItems)

        @SuppressLint("SetTextI18n", "UseKtx")
        fun bindMetadata(evidence: JSONObject) {
            metadataItems.removeAllViews()

            try {
                // Check if metadata exists in the evidence object
                if (evidence.has("metadata")) {
                    val metadataArray = evidence.getJSONArray("metadata")

                    if (metadataArray.length() == 0) {
                        metadataContainer.visibility = View.GONE
                        return
                    }

                    metadataContainer.visibility = View.VISIBLE

                    // Add each metadata item
                    for (i in 0 until metadataArray.length()) {
                        val metaItem = metadataArray.getJSONObject(i)
                        val key = metaItem.getString("meta_key")
                        val value = metaItem.getString("meta_value")

                        val textView = TextView(itemView.context).apply {
                            layoutParams = LinearLayout.LayoutParams(
                                LinearLayout.LayoutParams.MATCH_PARENT,
                                LinearLayout.LayoutParams.WRAP_CONTENT
                            ).apply {
                                bottomMargin = 2.dpToPx()
                            }
                            text = "• $key: $value"
                            textSize = 16f
                            setTextColor(Color.parseColor("#666666"))
                            maxLines = 2
                            ellipsize = android.text.TextUtils.TruncateAt.END
                        }

                        metadataItems.addView(textView)
                    }
                } else {
                    metadataContainer.visibility = View.GONE
                }
            } catch (_: Exception) {
                metadataContainer.visibility = View.GONE
            }
        }

        private fun Int.dpToPx(): Int {
            val density = itemView.context.resources.displayMetrics.density
            return (this * density).toInt()
        }
    }
}