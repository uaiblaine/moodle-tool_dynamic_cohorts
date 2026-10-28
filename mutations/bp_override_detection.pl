# BP: no condition class counts as filtering events, so the relevance filter is never asked.
s{self::\$filtersevents\[\$classname\] = \$declaring !== condition_base::class;}{self::\$filtersevents[\$classname] = false;}s;
