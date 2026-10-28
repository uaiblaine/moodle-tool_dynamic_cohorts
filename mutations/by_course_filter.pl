# BY: the one-course source reads every course.
s#\n            \$filter \.= " AND \{\$alias\}\.courseid = :\{\$courseparam\}";\n#\n#s;
