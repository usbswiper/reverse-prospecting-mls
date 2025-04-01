<?php
function getEmailBody($emailData)
{
    $imageFile = '';
    if (!empty($emailData['custom_image_file'])) {
        $imageFile = '<img src="' . BASE_URL . '/uploaded/' . $emailData['custom_image_file'] . '" style="max-width: 500px" width="500"/><br><br>';
    }
    $boldLineNotes = '';
    if (!empty($emailData['custom_email_notes_1'])) {
        $boldLineNotes = '<div style="color:#000000;font-weight: bold;margin: 5px 0">'.$emailData['custom_email_notes_1'].'</div><br><br>';
    }
    $htmlEmail = '<table width="100%s" border="0" cellpadding="0" cellspacing="0" style="font-size: 15px;line-height: 18px;">
<tr><td>%s
<div style="color:darkred;font-weight: bold">NOTE: You are receiving this because you have one or more searches set up that match my listing.</div><br><br>
%s<br>
<br>
Hi %s,<br>
<br>
According to MRED reverse prospecting, you have a search setup for a client who is looking for a home just like the one I listed at %s %s - MLS# %s<br>
<br>
My listing matches the number of bedrooms, baths, location and price point your clients are looking for as specified in your search criteria.<br>
<br>
Number of Bedrooms 		- %s<br><br>
Number of Bathrooms 	- %s<br><br>
Approximate Sq. Ft. 	- %s<br><br>
Listed at               - %s<br><br>%s
I hope you will let your client know about this home in case they want to schedule a showing.<br>
<br>
The details for you to be able to look up the property are:<br>
<br>
Address 		- %s<br>
<br>
City 		    - %s<br>
<br>
MLS# 			- %s<br>
<br>
Client Public ID	- %s<br>
<br>
Thank you and if you have any questions, please feel free to reach out to me!<br>
<br>
Cordially,<br><br>
</td></tr>
<tr><td>
<strong>Richard “RJ” Freedkin, Realtor</strong><br>
CRS, RENE, SRS, ABR, LHC<br>
eXp Realty, LLC<br>
10 N. Martingale Rd Suite 400<br>
Schaumburg, IL 60173<br>
<strong style="margin: 10px 0;">(847) 922-8423</strong><br>
<a href="https://www.rjfreedkin.com">www.rjfreedkin.com</a><br>
<a href="mailto:rjfreedkin@gmail.com">rjfreedkin@gmail.com</a><br>
<br>
<i>equal housing opportunity</i><br><br>
<div><img src="%s" style="width: 100px" width="150"/></div>
<div style="margin-top: 15px"><img src="%s" style="width: 100px" width="150"/></div>
<div style="margin-top: 15px"><img src="%s" style="width: 50px" width="50" /></div></td></tr>
<tr><td><br><br>
<strong style="font-size: 12px">CONFIDENTIALITY NOTICE:</strong> <span style="font-size: 12px">This e-mail message, including any attachments, is for the sole use of the intended recipient(s) and may contain confidential and privileged information protected by law. Any unauthorized review, use or distribution is prohibited. If you are not the intended recipient, please contact the sender by reply e-mail and destroy all copies of the original message.</span></td></tr>
<tr><td style="text-align: center"><br><br><a href="%s">Unsubscribe</a></td></tr>
</table>';

    $agentFirstName = explode(' ', $emailData['agentName']);
    return sprintf($htmlEmail, '%', $boldLineNotes, date('M d, Y'),
        $agentFirstName[0], $emailData['address'], $emailData['custom_city'], $emailData['mls'],
        $emailData['custom_bedrooms'], $emailData['custom_bathrooms'], $emailData['custom_sqft'], $emailData['custom_listed_at'],
        $imageFile, $emailData['address'], $emailData['custom_city'], $emailData['mls'], $emailData['clientPublicId'],
        IMAGE_BASE_URL.'/richard-image.png',
        IMAGE_BASE_URL.'/exp-realty-image-1.png',
        IMAGE_BASE_URL.'/equal_housing_logo-1.png',
        $emailData['unsubscribeLink']
    );
}