# How to create a new composer version

Once the feature branch is ready
1. Update the version number in composer.json
2. Merge the feature branch to master
3. Create a tag with the matching version number
4. Go to packagist and click on the update button

# Packagist Documentation

Tag/version names should match 'X.Y.Z', or 'vX.Y.Z', with an optional suffix for RC, beta, alpha or patch versions. Here are a few examples of valid tag names:

1.0.0
v1.0.0
1.10.5-RC1
v4.4.4beta2
v2.0.0-alpha
v2.0.4-p1
For a version 1.2.1, valid Git tag names would be either 1.2.1 or v1.2.1.