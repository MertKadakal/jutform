# Investigation Report

## Debugging Steps

1. **Pagination Audit:** Reviewed `SubmissionController.php` and its model equivalent to confirm the use of `LIMIT/OFFSET` for pagination.
2. **Drift Emulation:** Opened the submissions list for a form. While on Page 1, I manually inserted a new record via the database.
3. **Observation:** Navigated to Page 2 and verified that the last item from Page 1 was now the first item on Page 2, confirming the "record drift" caused by the `ORDER BY DESC` sort order.

## How to Reproduce

1. **Fill Data:** Ensure a form has at least 21 submissions (to test Page 1 and Page 2 with a limit of 20).
2. **Open Page 1:** View the first page of submissions. Take note of the ID of the 20th entry.
3. **Simulate Traffic:** Keeping the browser tab open, submit the form one more time (creating a new entry at the top of the list).
4. **Navigate to Page 2:** Click the link for "Page 2".
5. **Spot Duplicate:** You will see the entry you noted in step 2 appearing again at the top of Page 2.

## Root Cause

The system uses classic `OFFSET` pagination. Since submissions are sorted in descending order (`DESC`), any new record inserted during navigation shifts the indices of all existing records. This causes the "tail" of Page 1 to become the "head" of Page 2, leading to duplicate rows being displayed to the user.

## Fix Description

I will implement "Snapshot Pagination" (also known as Time-anchored pagination). The API will now accept an optional `until` timestamp. When a user starts browsing Page 1, we will lock the results to that specific point in time. Page 2, 3, etc., will only query records created *before* the snapshot time. This ensures that new incoming submissions do not shift the offsets of the data the user is currently reviewing.

## Response to Reporter

> Hi poweruser,
> 
> Thank you for this catch! You're absolutely right—since new responses keep coming in, "old" responses were getting pushed down a row while you were browsing, making them appear twice.
> 
> I've updated the system to "lock" your view to a specific moment when you start browsing. Now, even if hundreds of new submissions arrive while you are on page 2, they won't jumble up your current list. You can refresh the page whenever you're ready to see the latest updates!
