# Investigation Report

## Debugging Steps

1. **Endpoint Profiling:** Used browser DevTools and XHProf to profile the `/api/forms` endpoint. Confirmed that time spent in the database layer increases linearly with the number of forms.
2. **Database Query Logging:** Enabled general query logging in MySQL and observed that for a user with $N$ forms, the application was executing $2N + 1$ queries.
3. **N+1 Identification:** Pinpointed the loop in `FormController::index` that was fetching submission counts and last submission dates individually for each form in the collection.

## How to Reproduce

1. **Mass Form Creation:** Using a script or the database CLI, create at least 150 forms for a single user (e.g., `poweruser`).
2. **Add Submissions:** Ensure some of these forms have existing submission data.
3. **Load Dashboard:** Log in as that user and visit the "My Forms" dashboard.
4. **Benchmark:** Use a stopwatch or browser DevTools. Without the fix, the page will hang for 10+ seconds while the server executes hundreds of sequential sub-queries to tally submission counts.

## Root Cause

The application suffered from an "N+1 Query" pattern. Instead of fetching all required data in a single optimized query, the `FormController` was making additional database calls inside a `foreach` loop for every form owned by the user. As users created more forms, the number of database roundtrips grew beyond sustainable limits.

## Fix Description

I have refactored the `Form` model to include a new method: `findByUserWithStats($userId)`. This method uses SQL subqueries to fetch the `submission_count` and `last_submission_at` directly within the main `SELECT` statement from the `forms` table. This reduces the number of database queries for the "My Forms" page from $2N+1$ down to exactly **1**.

## Response to Reporter

> Hi poweruser,
> 
> Thank you for your feedback. We analyzed our backend performance and discovered that the system was being inefficient by asking the database for submission counts one form at a time.
> 
> We have implemented a new data-fetching technique called "Query Optimization." Instead of hundreds of small requests, the application now gathers all your form data and submission stats in a single, lightning-fast operation. Whether you have 10 forms or 1,000, your dashboard should now load almost instantly.
