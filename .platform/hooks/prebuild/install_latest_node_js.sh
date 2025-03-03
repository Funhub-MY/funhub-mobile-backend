#!/bin/sh

# Install Latest Node

# Some Laravel apps need Node & NPM for the frontend assets.
# This script installs the latest Node 16.x alongside
# with the paired NPM release.
# Added check to avoid reinstallation if Node.js is already installed

# Check if Node.js is already installed
if command -v node >/dev/null 2>&1; then
  NODE_VERSION=$(node -v)
  echo "Node.js is already installed (${NODE_VERSION}). Skipping installation."
else
  echo "Node.js not found. Installing Node.js 16.x..."
  
  # Clean up any previous installations
  sudo yum remove -y nodejs npm
  sudo rm -fr /var/cache/yum/*
  sudo yum clean all
  
  # Install Node.js 16.x
  curl --silent --location https://rpm.nodesource.com/setup_16.x | sudo bash -
  sudo yum install nodejs -y
  
  echo "Node.js installation completed."
fi

# Uncomment this line and edit the Version of NPM
# you want to install instead of the default one.
# npm i -g npm@6.14.4
