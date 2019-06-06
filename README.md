# com.aghstrategies.activityemail
Upon creation of an activity of type X, send text Y (including information from the activity) to smart group Z.

## Configuration
1. Go to the `civicrm/activityemail` settings page
  + Wordpress link: {url}/wp-admin/admin.php?page=CiviCRM&q=civicrm%2Factivityemail
  + Drupal link: {url}/civicrm/activityemail
2. Select the Activity Type and the corresponding Groups To Email, From Email Address, and Message Template. The "Submit/Add More" button will save AND add a new row to add a new Activity Type see screenshot below:
![screenshot of settings page.](images/settingsPage.png)

## How It Works
When an Activity is created of a type that is set up on the settings page with a corresponding "Group/Groups To Email" then a copy of that activity will be sent to each member of the corresponding Groups To Email from the From Email using the Message Template set for that row.

## Tokens Available to the Message Template

### Example
If the settings are configured as they are in the Screenshot above then:

>**When** an Activity of type "Phone Call" is created  
>**Then** an Email will be sent to all members of the Administrator Group Primary email addresses using the Message Template "Test" from the Contact "1,1".

### Wishlist features
1. Allow multiple rows per activity type
2. Fix Message Template input field Search
