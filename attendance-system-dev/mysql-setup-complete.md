# 🚀 Complete MySQL Attendance System Setup Guide

## 📋 **What You Get**
✅ **Beautiful UI** - Same amazing design you loved  
✅ **MySQL Database** - Professional data storage  
✅ **Range-based Office Selection** - Only in-range offices enabled  
✅ **Employee vs Admin Access** - Only admin can export all records  
✅ **All Business Rules** - WFH limits, half-day detection, GPS validation  

---

## 📁 **Files Provided**

1. **[108] `mysql-database-setup.sql`** - Complete database schema
2. **[109] `api-mysql.php`** - Backend API with MySQL integration
3. **[110] `index-mysql-powered.html`** - Frontend with beautiful UI
4. **This setup guide**

---

## 🛠️ **Setup Process**

### **Step 1: Database Setup**

#### **Method A: Using phpMyAdmin (Easiest)**
1. Open phpMyAdmin in browser: `http://localhost/phpmyadmin/`
2. Click **"SQL"** tab at the top
3. Copy entire content from `mysql-database-setup.sql`
4. Paste in SQL box and click **"Go"**
5. ✅ Done! Database created with sample data

#### **Method B: Using MySQL Command Line**
```bash
mysql -u root -p < mysql-database-setup.sql
```

### **Step 2: Configure Database Connection**

Edit `api-mysql.php` (lines 37-40):
```php
private $host = 'localhost';           // Your MySQL host
private $db_name = 'attendance_system'; // Database name (don't change)
private $username = 'root';            // Your MySQL username
private $password = '';                // Your MySQL password
```

**Common Configurations:**
- **XAMPP:** username: `root`, password: `` (empty)
- **WAMP:** username: `root`, password: `` (empty) 
- **MAMP:** username: `root`, password: `root`
- **Custom:** Use your MySQL credentials

### **Step 3: Deploy Files**

Create folder structure:
```
attendance-system/
├── index.html (from index-mysql-powered.html)
├── api.php (from api-mysql.php)
└── mysql-database-setup.sql (reference)
```

**File Locations:**
- **XAMPP:** `C:\xampp\htdocs\attendance-system\`
- **WAMP:** `C:\wamp64\www\attendance-system\`
- **Linux:** `/var/www/html/attendance-system/`

### **Step 4: Start Services**

#### **XAMPP Users:**
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**
3. Open: `http://localhost/attendance-system/`

#### **WAMP Users:**
1. Start WAMP server
2. Wait for green icon
3. Open: `http://localhost/attendance-system/`

---

## 🎯 **Key Features Implemented**

### **✅ Range-Based Office Selection**
- **GPS location detection** automatically
- **Only in-range offices** are enabled for selection
- **Visual indicators** show distance and range status
- **Disabled state** for out-of-range offices

### **✅ Role-Based Access Control**
- **Employees:** Can only see their own records
- **Admin:** Can see all employee records and export data
- **Export function** restricted to admin users only

### **✅ Enhanced Business Logic**
- **WFH monthly limit** (1 per month) with real-time validation
- **Half-day detection** with automatic status update
- **Department-based office access** (Surveyors only 79 office)
- **GPS validation** with 50-meter radius enforcement

---

## 🔑 **Login Credentials**

### **Default Accounts:**
- **Admin:** `admin` / `password`
- **Employee:** `john.doe` / `password`
- **Employee:** `jane.smith` / `password`
- **Surveyor:** `mike.wilson` / `password` (only 79 office)

### **Test Different Roles:**
Each account has different department and office access to test all features.

---

## 📱 **How It Works**

### **🏢 Office Selection Logic**
1. System gets user's GPS location
2. Calculates distance to all accessible offices
3. **Enables** offices within 50m range
4. **Disables** offices outside range
5. Shows visual status: "In Range" or "Out of Range"

### **👤 Employee Access**
- Can mark attendance at accessible offices
- Can view own attendance records
- Cannot export all employee data

### **👑 Admin Access**
- Can see all employee records
- Can export complete attendance data
- Has access to system-wide statistics

---

## 🔧 **Database Features**

### **📊 Pre-loaded Data**
- ✅ **4 sample employees** with different departments
- ✅ **2 office locations** (79 & 105) with GPS coordinates
- ✅ **Department access rules** configured
- ✅ **Sample attendance records** for testing

### **🚀 Advanced Features**
- ✅ **Stored procedures** for complex operations
- ✅ **Database views** for easy reporting
- ✅ **Indexes** for fast queries
- ✅ **JSON fields** for location data
- ✅ **Foreign key constraints** for data integrity

---

## 🧪 **Testing Scenarios**

### **Test 1: Range-Based Access**
1. Login as `mike.wilson` (Surveyor)
2. Start attendance flow
3. See only 79 office (Surveyors restriction)
4. Test GPS validation

### **Test 2: WFH Limits**
1. Login as any employee
2. Try to select WFH option
3. See monthly usage counter
4. Test limit enforcement

### **Test 3: Admin Functions**
1. Login as `admin`
2. Go to records screen
3. See all employee data
4. Test export function

### **Test 4: Half-Day Detection**
1. Mark attendance in morning
2. Check out after <8 hours
3. See half-day warning
4. Confirm automatic status change

---

## 📊 **Database Tables**

### **Key Tables Created:**
- `employees` - User accounts and profiles
- `office_locations` - GPS coordinates and settings
- `attendance_records` - Daily attendance data
- `department_office_access` - Access control rules
- `wfh_requests` - WFH approval workflow

### **Views & Procedures:**
- `employee_attendance_summary` - Easy reporting
- `monthly_attendance_stats` - Aggregated data
- `GetAccessibleOffices()` - Department-based filtering
- `CheckWFHEligibility()` - Monthly limit validation

---

## 🚨 **Troubleshooting**

### **Database Connection Issues:**
```
Error: "Database connection failed"
```
**Solution:** Check MySQL credentials in `api.php` lines 37-40

### **API Not Working:**
```
Error: "Network error"
```
**Solutions:**
- Ensure Apache is running
- Test: `http://localhost/attendance-system/api.php/offices?department=IT`
- Check browser console for errors

### **Office Selection Empty:**
```
No offices shown
```
**Solutions:**
- Check database has office data
- Verify GPS permissions in browser
- Test with different department

### **GPS Not Working:**
```
Location error
```
**Solutions:**
- Use `https://` or `localhost` (required for GPS)
- Allow location permissions in browser
- Test on different device/browser

---

## 🎉 **Ready to Demo!**

Your system now has:

✅ **Professional Design** - Beautiful UI with perfect styling  
✅ **MySQL Backend** - Enterprise-grade data storage  
✅ **Smart Office Selection** - Only in-range offices enabled  
✅ **Role-Based Access** - Admin export restrictions  
✅ **Business Logic** - All requirements implemented  
✅ **Mobile Responsive** - Works on all devices  

**Demo URL:** `http://localhost/attendance-system/`  
**Admin Login:** `admin` / `password`  

Perfect for showing your senior a complete, professional attendance management system! 🚀