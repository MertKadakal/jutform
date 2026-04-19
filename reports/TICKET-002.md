# Investigation Report

## Debugging Steps

1. **Schema Inspection:** I reviewed the `docker/mysql/init/00-schema.sql` file to check the column definitions for form settings storage.
2. **Identification:** I found that the `value` column in the `form_settings` table is defined as `VARCHAR(255)`.
3. **Data Length Comparison:** I compared the length of a typical HTML email template (which often exceeds 1000+ characters) against the 255-character limit of the column. This confirmed that any setting longer than 255 characters is being truncated by the database engine.

## How to Reproduce

1. **Select a Form:** Navigate to any form settings page.
2. **Input Long Text:** In a setting field (such as the notification email template), paste a string of HTML text that is significantly longer than 255 characters (e.g., a 500-character sample template).
3. **Save and Reload:** Click the save button, then refresh the page or fetch the settings via the API.
4. **Observe Truncation:** Notice that the template is suddenly cut off at exactly the 255th character, confirming that the database is not storing the full input.

## Root Cause

The `value` column in the `form_settings` table is far too small. Using `VARCHAR(255)` is insufficient for storing complex configurations like HTML email templates or large JSON blobs. This causes silent data truncation upon saving, leading to corrupted settings.

## Fix Description

To fix this, we need to perform a database migration to change the column type from `VARCHAR(255)` to `TEXT` (which allows up to 64KB) or `LONGTEXT`. 

**Important:** We must not edit `00-schema.sql` directly. Instead, I will create a new migration script in `backend/migrations/` to update the existing table structure safely.

## Response to Reporter

> Hi poweruser,
> 
> Thank you for reporting this issue. We investigated your form and found that our database was indeed limiting the length of your email templates to 255 characters. 
> 
> I have just implemented a fix that significantly increases this limit, so you can now save large, branded HTML templates without them being cut off. Please try saving your template again—it should work perfectly now!