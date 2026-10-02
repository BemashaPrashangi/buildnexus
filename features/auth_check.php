<?php
// Session එක දැනටමත් ආරම්භ වී ඇත්දැයි පරීක්ෂා කර, නැතිනම් පමණක් ආරම්භ කරයි.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. පරිශීලකයා පද්ධතියට ලොග් වී ඇත්දැයි පරීක්ෂා කිරීම (Authentication)
if (!isset($_SESSION['user_id'])) {
    // ලොග් වී නැතිනම් login පිටුවට යොමු කරයි.
    // ඔබගේ folder structure එකට අනුව path එක නිවැරදි බව සහතික කරගන්න.
    header("Location: /buildnexus/login.php");
    exit();
}

/**
 * 2. භූමිකාවන් (Roles) පරීක්ෂා කිරීමේ ශ්‍රිතය (Authorization)
 * මෙමගින් ලොග් වී සිටින පරිශීලකයාට අදාළ පිටුව බැලීමට අවසර තිබේදැයි බලයි.
 */
function checkRole($allowed_roles) {
    // පරිශීලකයාගේ Role එක අවසර ලත් ලැයිස්තුවේ (Allowed Roles Array) තිබේදැයි පරීක්ෂා කරයි.
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        
        // අවසර නොමැති නම් පණිවිඩයක් පෙන්වා නැවත ප්‍රධාන පිටුවට යොමු කරයි.
        echo "<script>
                alert('Access Denied: මෙම පිටුවට පිවිසීමට ඔබට අවසර නැත.');
                window.location.href = '/buildnexus/index.php'; 
              </script>";
        exit();
    }
}
?>