# Caching schema

It may not be worth it purely for performance reasons (currently takes ~10-20 msec), but it would be useful for debugging or schema optimization (DSPy like).

Schema could be saved in version controlled, versioned JSON files and loaded from there. In development mode it would be read from JSON file, unless class file is newer than schema file.
