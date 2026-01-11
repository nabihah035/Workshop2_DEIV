package com.example.deiv.api

object ApiService {
    // Fixed URL to match the exact folder names: Workshop2_DEIV and AndriodStudioCode
    const val BASE_URL = "http://172.26.83.131/deiv_api/"

    // Case related endpoints
    const val CASE_REGISTER = "${BASE_URL}case_reg.php"
    const val CASE_LIST = "${BASE_URL}case_list.php"
    const val CASE_DETAIL = "${BASE_URL}case_detail.php"
    // Evidence related endpoints
    const val EVIDENCE_UPLOAD = "${BASE_URL}upload_evidence.php"
    // Home/dashboard endpoints
    const val HOME_DATA = "${BASE_URL}home_page.php"
    // User profile endpoint
    const val USER_PROFILE = "${BASE_URL}user_profile.php"
    const val UPDATE_PROFILE = "${BASE_URL}update_profile.php"
    const val CHANGE_PASSWORD = "${BASE_URL}change_password.php"
    //register
    const val REGISTER = "${BASE_URL}register.php"
    //login
    const val LOGIN = "${BASE_URL}login.php"
    const val FORGOT_PASSWORD = "${BASE_URL}forgot_password.php"
    //Logout
    const val LOGOUT = "${BASE_URL}logout.php"

    // Notification endpoints
    const val NOTIFICATION_LIST = "${BASE_URL}notification_list.php"
    const val NOTIFICATION_MARK_READ = "${BASE_URL}notification_mark_read.php"

}
