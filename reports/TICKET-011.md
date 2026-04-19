# Investigation Report

## Debugging Steps

1. **Static Code Analysis:** I examined the `SubmissionController::index` method to understand how it handles authorization for shared forms. I noticed a call to a helper function named `checkFormOwnerPermission`.
2. **Logic Tracing:** I traced the definition of `checkFormOwnerPermission` in `backend/src/Helpers/functions.php`. I discovered that this function explicitly overwrites the `RequestContext::$currentUserId` (which holds the logged-in user's ID) with the ID of the form owner.
3. **State Leakage Verification:** I followed the request lifecycle after that call and found that subsequent logic—specifically the sidebar fetching ("related forms")—uses the now-mutated `RequestContext::$currentUserId`.
4. **Conclusion:** This confirms a **Global State Mutation** bug. When a collaborator views a shared form, the system briefly forgets who the actual visitor is and treats them as the form owner for the rest of the page load, causing other users' form titles and metadata to leak into the UI.

## How to Reproduce

1. **Setup Two Users:** **Alice (ID: 3)** and **Bob (ID: 4)**.
2. **Create Content:** Log in as Alice and create multiple forms.
3. **Establish Sharing:** Manually share one of Alice's forms (e.g., Form ID: 201) with Bob. Since the UI lacks a share button, insert the following record into the database:
   `INSERT INTO app_config (config_key, value) VALUES ('shared_with_user_ids_201', '[4]');`
4. **Access Shared Form:** Log in as Bob and navigate to the submissions page of Alice's shared form: `/api/forms/201/submissions`.
5. **Observe Leakage:** Look at the `related_forms` array in the JSON response (or the related forms sidebar in the UI). You will notice that it lists **Alice's forms (the owner)** instead of Bob's forms (the current visitor), confirming the cross-account data leak.

## Root Cause

The root cause of this issue is a **Global State Mutation** bug within the `SubmissionController::index` method. The function relies on a helper, `checkFormOwnerPermission`, which directly overwrites the global `RequestContext::$currentUserId` with the ID of the form owner. This modification persists for the remainder of the request cycle. Consequently, when the application subsequently attempts to fetch "related forms" for the sidebar, it queries using the form owner's ID instead of the actual visitor's ID. This results in the system leaking sensitive metadata—specifically, the titles and IDs of the form owner's private forms—to the wrong user.

## Fix Description

I have resolved this issue by refactoring the `SubmissionController::index` method to eliminate the reliance on the global state-mutating helper function. The fix involves extracting the necessary permission logic into a local variable within the method scope. This ensures that the `RequestContext::$currentUserId` remains unchanged and accurately reflects the identity of the current visitor throughout the request. As a result, the sidebar now correctly displays the related forms belonging to the actual user, and the cross-account data leak has been successfully prevented.

## Response to Reporter

> Hi Alice,
>
> I've investigated the issue you reported regarding the related forms sidebar. It appears there was a bug where the system was incorrectly displaying the form owner's details instead of the current user's. I've implemented a fix to ensure that the sidebar now shows the correct information for the logged-in user. Thank you for bringing this to our attention!
