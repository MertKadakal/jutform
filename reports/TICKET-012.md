# Investigation Report

## Debugging Steps

1. **URL Manipulation**: I attempted to access the advanced search endpoint with a non-existent column name (`not_existing_column`) to test the system's validation. The request was: `curl "http://localhost:8080/api/search/advanced?field=not_existing_column&term=test"`.
2. **Log Monitoring**: I monitored the application logs using `./scripts/logs.sh app` to observe any SQL syntax errors or database engine warnings resulting from the manipulated requests.
3. **Data Extraction Test**: I tested for data exfiltration by attempting to access normally restricted data using `OR 1=1` in the query parameters to see if the vulnerability could be exploited to bypass permissions.

## How to Reproduce

If an attacker calls the following URL: /api/search/advanced?field=title=1 OR 1=1 --

The resulting SQL query becomes: SELECT * FROM forms WHERE title=1 OR 1=1 -- LIKE '%...%' AND user_id = 3

Result: Thanks to OR 1=1, the query always returns true, and the attacker can see all forms that do not belong to them. They can even go further and steal user passwords (hashes) or system settings using UNION SELECT commands.

## Root Cause

1. **Column Name Injection:** The application takes the value sent by the user with the field parameter (for example, "title" or "description") and places it directly into the SQL query ({$field}).
2. **Insufficient Security:** The system does not check whether the user is actually sending a valid column name. An attacker can inject SQL commands into the field parameter to steal all data in the database.

## Fix Description

1. **Input Validation**: I added a validation step to check if the field parameter contains only valid column names. This prevents SQL injection attacks by ensuring that only allowed column names are used in the query.
2. **Prepared Statements**: I updated the SQL query to use prepared statements instead of string concatenation. This ensures that user input is treated as data, not as executable code, further preventing SQL injection attacks.

## Response to Reporter

> Hi Security Team,
> Thank you for reporting the vulnerability in the advanced search endpoint. I have completed the investigation and confirmed that the `field` parameter was indeed susceptible to SQL injection.
> I have implemented a two-layered defense:
> 1. **Strict Whitelisting:** The `field` parameter is now validated against a fixed list of allowed columns (`id`, `title`, `description`, `status`). Any other value defaults to `title`, preventing column-name injection.
> 2. **Parameterized Queries:** We have migrated the entire query construction to use PDO prepared statements. This ensures that the search term and user ID are handled as data, eliminating the risk of command injection through the `term` parameter.
The endpoint is now secure and adheres to our security best practices. We appreciate your diligent review!