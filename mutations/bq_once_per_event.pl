# BQ: a rule is queued once per subscribed condition class instead of once per event.
s{\$toprocess\[\$ruleid\] = \$rule;}{\$toprocess[] = \$rule;}s;
