package com.example.deiv.api

object ApiService {
    const val BASE_URL = "http://172.26.83.131/deiv_api/"

    // Case related endpoints
    const val CASE_REGISTER = "${BASE_URL}case_reg.php"
    const val CASE_LIST = "${BASE_URL}case_list.php"
    const val CASE_DETAIL = "${BASE_URL}case_detail.php"

    // Evidence related endpoints
    const val EVIDENCE_UPLOAD = "${BASE_URL}upload_evidence.php"

    // Home/dashboard endpoints
    const val HOME_DATA = "${BASE_URL}home_page.php"
}