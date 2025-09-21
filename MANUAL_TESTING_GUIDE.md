# Manual Testing Guide - Nexio Collabora

## 🎯 Quick Testing Checklist

### Test Credentials
- **Email**: `asamodeo@fortibyte.it`
- **Password**: `Ricord@1991`

---

## 🌐 Test URLs

### 1. Authentication Test
**Login Page**: http://localhost/Nexiosolution/collabora/index_v2.php
- ✅ Test admin login with credentials above
- ✅ Verify redirect to dashboard after login
- ✅ Check no JavaScript errors in console (F12)

### 2. Core Pages Navigation
**Dashboard**: http://localhost/Nexiosolution/collabora/dashboard.php
- ✅ Verify anthracite sidebar (#111827)
- ✅ Check all navigation links work
- ✅ Confirm layout is responsive

**Calendar**: http://localhost/Nexiosolution/collabora/calendar.php
- ✅ Verify unified layout structure
- ✅ Check calendar interface loads
- ✅ Test "Nuovo Evento" button
- ✅ Confirm no console errors

**Tasks**: http://localhost/Nexiosolution/collabora/tasks.php
- ✅ Verify unified layout structure
- ✅ Check task board interface
- ✅ Test board selector functionality
- ✅ Confirm no console errors

**Chat**: http://localhost/Nexiosolution/collabora/chat.php
- ✅ Verify unified layout structure
- ✅ Check chat interface loads
- ✅ Test channels sidebar
- ✅ Confirm no console errors

### 3. Admin Panel
**Admin Dashboard**: http://localhost/Nexiosolution/collabora/admin/index.php
- ✅ Verify admin access (requires admin role)
- ✅ Check dashboard widgets load
- ✅ Test Chart.js graphics (should load locally)
- ✅ Confirm no CSP errors

**User Management**: http://localhost/Nexiosolution/collabora/admin/users.php
- ✅ Verify user list loads
- ✅ Test user management functions
- ✅ Check multi-tenant data isolation

---

## 🔍 Browser Console Checks

### What to Look For
1. **Open Developer Tools** (F12)
2. **Go to Console tab**
3. **Reload each page**
4. **Verify**:
   - ❌ No "Unexpected token 'export'" errors
   - ❌ No 404 errors for JavaScript files
   - ❌ No 500/401/400 API errors
   - ❌ No failed resource loads

### Expected Results
- **Calendar.js**: ✅ Loads without export errors
- **Tasks.js**: ✅ Loads without export errors
- **Chat.js**: ✅ Loads without export errors
- **Chart.js**: ✅ Loads from local path `/assets/js/vendor/`

---

## 📱 Responsive Design Test

### Mobile Testing
1. **Open Developer Tools** (F12)
2. **Click device toolbar** (Ctrl+Shift+M)
3. **Select iPhone/Android** viewport
4. **Test each page**:
   - Dashboard
   - Calendar
   - Tasks
   - Chat

### What to Verify
- ✅ Sidebar collapses on mobile
- ✅ Content is readable and accessible
- ✅ Buttons and links are touchable
- ✅ No horizontal scrolling
- ✅ Layout adapts properly

---

## ⚡ Basic Functionality Tests

### Calendar Module
1. Navigate to calendar.php
2. Click "Nuovo Evento" button
3. Verify modal/form opens
4. Check date navigation works

### Tasks Module
1. Navigate to tasks.php
2. Check board selector dropdown
3. Test "Crea nuovo board" button
4. Verify task board layout

### Chat Module
1. Navigate to chat.php
2. Check channels sidebar loads
3. Verify message input area
4. Test chat interface responsiveness

---

## 🧪 Automated Testing

### Run Validation Script
**URL**: http://localhost/Nexiosolution/collabora/validate_end_to_end.php

This script will automatically test:
- Database connectivity
- Authentication system
- UI structure consistency
- JavaScript export issues
- API endpoint syntax
- File structure validation

### Expected Result
- **Success Rate**: 100%
- **Status**: "EXCELLENT" or "GOOD"
- **Failed Tests**: 0

---

## 🚨 Common Issues to Watch For

### If You See JavaScript Errors
- Check for ES6 export statements (should be none)
- Verify all scripts load from correct paths
- Confirm no external CDN dependencies

### If You See PHP Errors
- Check SimpleAuth includes are correct
- Verify database connection is working
- Confirm session handling is proper

### If You See Layout Issues
- Verify anthracite theme (#111827) on sidebar
- Check app-layout structure is consistent
- Confirm responsive CSS is loaded

---

## ✅ Success Criteria

### Green Light Indicators
- ✅ Login works with admin credentials
- ✅ All pages load without errors
- ✅ Console shows no JavaScript errors
- ✅ Layout is consistent across pages
- ✅ Mobile responsive design works
- ✅ Admin panel is accessible
- ✅ Chart.js loads locally without CSP errors

### If All Tests Pass
**Status**: **✅ SYSTEM READY FOR PRODUCTION**

The Nexio Collabora system is fully operational and ready for deployment.