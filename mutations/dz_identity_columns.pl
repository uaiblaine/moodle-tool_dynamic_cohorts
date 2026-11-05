# DZ: The matching-users report shows username, email and idnumber in every context.
s{if \(\$context\-\>contextlevel \=\= CONTEXT_SYSTEM\) \{}{if (true) \{}s;
