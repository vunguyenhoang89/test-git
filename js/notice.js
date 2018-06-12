jQuery(document).ready(function() {
    jQuery('#system_notice_area').animate({
        opacity : 'show',
        height : 'show'
    }, 500);

    jQuery('#system_notice_area_dismiss').click(function() {
        jQuery('#system_notice_area').animate({
            opacity : 'hide',
            height : 'hide'
        }, 500);

    });

    jQuery('#xyz_smap_twmessage').keyup(function () {
        var text = jQuery(this).val();
        var textLength = text.length;
        var maxLength = 30;
           if (textLength == maxLength) {
               alert("ハッシュタグ欄は"+maxLength+"文字以下で入力してください。");
           }
    });
    jQuery('#xyz_smap_twmessage_title').keyup(function () {
        var text = jQuery(this).val();
        var textLength = text.length;
        var maxLength = 70;
            if (textLength == maxLength) {
                alert("ハッシュタグ欄は"+maxLength+"文字以下で入力してください。");
            }
    });
});
