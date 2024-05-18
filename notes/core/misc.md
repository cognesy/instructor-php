# Instructor init

Currently, Instructor uses Configuration::fresh() to always get new instance of
configuration. If switched to auto(), which returns singleton instance, we get
errors in tests - there's some problem to be diagnosed, its unclear why it happens.
