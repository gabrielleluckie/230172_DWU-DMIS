-- Dev Quick-Login test users for local development (APP_ENV=local).
-- Aligns emails with includes/dev-auth.php and updates display names.
-- Safe to re-run: uses User_ID + Role as stable keys.

UPDATE users
SET Email = 'admin.madang@dwu.ac.pg',
    First_Name = 'Alois',
    Last_Name = 'Sanki',
    Campus_ID = 1,
    Is_Active = 1
WHERE User_ID = 1 OR (Role = 'campus_admin' AND Campus_ID = 1);

UPDATE users
SET Email = 'admin.pom@dwu.ac.pg',
    First_Name = 'Theresa',
    Last_Name = 'Pomaleu',
    Campus_ID = 2,
    Is_Active = 1
WHERE User_ID = 2 OR (Role = 'campus_admin' AND Campus_ID = 2 AND Email <> 'admin.madang@dwu.ac.pg');

UPDATE users
SET Email = 'admin.wewak@dwu.ac.pg',
    First_Name = 'William',
    Last_Name = 'Luke',
    Campus_ID = 3,
    Is_Active = 1
WHERE User_ID = 3 OR (Role = 'campus_admin' AND Campus_ID = 3);

UPDATE users
SET Email = '230172student@dwu.ac.pg',
    First_Name = 'Mary',
    Last_Name = 'Robinson',
    Campus_ID = NULL,
    Is_Active = 1
WHERE User_ID = 4 OR Role = 'partnership_director';

UPDATE users
SET Email = 'exec.officer@dwu.ac.pg',
    First_Name = 'Karen',
    Last_Name = 'Reeves',
    Campus_ID = NULL,
    Is_Active = 1
WHERE User_ID = 5 OR Role = 'executive_officer';

UPDATE users
SET Email = 'president@dwu.ac.pg',
    First_Name = 'Philip',
    Last_Name = 'Gregory',
    Campus_ID = NULL,
    Is_Active = 1
WHERE User_ID = 6 OR Role = 'president';
