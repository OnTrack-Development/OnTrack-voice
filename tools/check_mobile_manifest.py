#!/usr/bin/env python3
"""Fail on unnecessary notification/SMS/accessibility access in FINAL APK."""
import sys
from pathlib import Path
from xml.etree import ElementTree as ET
root = ET.parse(sys.argv[1]).getroot()
a = '{http://schemas.android.com/apk/res/android}'
forbidden = {'android.permission.BIND_NOTIFICATION_LISTENER_SERVICE',
             'android.permission.BIND_ACCESSIBILITY_SERVICE',
             'android.permission.READ_SMS', 'android.permission.RECEIVE_SMS'}
for node in root.iter():
    for value in node.attrib.values():
        if value in forbidden or 'NotificationListenerService' in value or 'DebugNotificationCleaner' in value:
            raise SystemExit('Unexpected notification/SMS/accessibility capability: ' + value)
print('PASS: final APK manifest has no notification listener, SMS, or Accessibility capability')
