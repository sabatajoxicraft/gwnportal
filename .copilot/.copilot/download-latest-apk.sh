#!/bin/bash
# Download latest APK from GitHub Releases
# Usage: ./download-latest-apk.sh owner/repo

REPO=${1:-$(git remote get-url origin | sed 's/.*github.com[:/]\(.*\)\.git/\1/')}
API_URL="https://api.github.com/repos/$REPO/releases/latest"

echo "Fetching latest release from $REPO..."
APK_URL=$(curl -s $API_URL | grep "browser_download_url.*apk" | cut -d '"' -f 4)

if [ -z "$APK_URL" ]; then
  echo "No APK found in latest release"
  exit 1
fi

echo "Downloading: $APK_URL"
curl -L -o app-release.apk "$APK_URL"
echo "Downloaded: app-release.apk"