# 🏷️ Auction Web Application

A full-featured online auction platform built with PHP and MySQL, featuring real-time bidding, user authentication, watchlists, seller reviews, and automated email notifications.

## ✨ Features

### Core Functionality
- 🔐 **User Authentication**: Secure registration and login system with session management
- 📦 **Auction Listings**: Browse, search, and filter auctions by category, price, and date
- 💰 **Real-time Bidding**: Place bids with automatic validation and conflict detection
- ⭐ **Watchlist System**: Save favorite auctions for quick access
- 🔔 **Email Notifications**: Automated alerts for outbid notifications, auction wins, and auction endings
- 📧 **Contact System**: Message sellers directly about auction items
- ⏰ **Auction Timer**: Live countdown for auction end times
- 📊 **User Dashboard**: Comprehensive view of bids, listings, and watchlist

### Advanced Features
- 🌟 **Seller Rating System**: Rate and review sellers after auction completion
- 🔍 **Advanced Search**: Filter by categories, price range, and auction status
- 📱 **Responsive Design**: Fully optimized for desktop, tablet, and mobile devices
- 🖼️ **Image Upload**: Support for item photos with preview functionality
- 📈 **Bid History**: Track all bids placed on an item
- 🏆 **Winning Notifications**: Email alerts when you win an auction
- 🚨 **Outbid Alerts**: Instant notifications when another user outbids you

### Complete Auction Lifecycle Interface
This provides a complete auction lifecycle from creation to closing, including:
- Create new auctions with detailed information
- Edit existing auctions before bids are placed
- Monitor bid activity in real-time
- Automatic auction closing when end date is reached
- Winner notification system
- Post-auction seller rating and review

### Full Photo Management
Sellers have comprehensive photo management capabilities:
- **Update, replace, delete auction photos** at any time before bids are placed
- **Store images** either as file paths or Base64 blobs for flexibility
- **System auto-checks** for photo_base64 column and adds if missing
- Support for multiple image formats (JPEG, PNG, GIF)
- Image preview functionality during upload
- Automatic image optimization and resizing

### Seller Rating & Review System
Winning buyers can provide comprehensive feedback through:
- **⭐ Ratings (1–5)**: Star-based rating system for quick evaluation
- **📝 Written reviews**: Detailed feedback about the transaction experience
- Average rating display on seller profiles
- Review moderation and reporting system
- Chronological review history
- Seller response capability

## 🛠️ Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: 
  - Tailwind CSS (utility-first framework)
  - Bootstrap 4.5.1 (components)
- **Email**: PHPMailer 6.0+
- **Icons**: Heroicons
- **Version Control**: Git

## 📋 Prerequisites

Before you begin, ensure you have met the following requirements:

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependency management)
- SMTP server for email notifications (or use Gmail SMTP)

## 🛠️ Installation Guide

### 1. Install Dependencies
Ensure your system has:
- PHP 8+
- MySQL / MariaDB
- Apache / Nginx (or PHP built-in server)
- Composer (optional)

### 2. Create MySQL Database
```sql
CREATE DATABASE auction_app;
```

### 3. Import Database Schema
```bash
mysql -u root -p auction_app < schema.sql
```

### 4. Configure Database Connection
Edit `database.php`:
```php
$host = "localhost";
$dbname = "auction_app";
$username = "root";
$password = "";
```

### 5. Configure Email Settings
```php
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
$mail->Port = 587;
```

### 6. Set File Permissions
```bash
chmod 755 img/uploads/
```

### 7. Optional: Configure Virtual Host
```apache
<VirtualHost *:80>
    ServerName auction.local
    DocumentRoot /var/www/auction-app
</VirtualHost>
```

### 8. Start Server
#### PHP Built-in:
```bash
php -S localhost:8000
```

#### XAMPP/WAMP/MAMP:
Visit:
```
http://localhost/auction-app
```

---

# 📁 Project Structure
```
/project
│── src/
│── img/uploads/
│── database.php
│── index.php
│── README.md
│── schema.sql
```

---

# 💡 Usage

## For Buyers
### Register an Account
- Go to registration page  
- Enter username, email, password  
- Verify email address  

### Browse Auctions
- View active auctions  
- Filter by price, date, category  
- Search items  

### Place Bids
- Open auction details  
- Enter a higher bid  
- Submit and get confirmation  

### Watchlist
- Click ❤️ to save auctions  
- Manage via Watchlist page  

### Track Bids
- View all your bids  
- Get notifications when outbid  
- Get notified when you win  

---

## For Sellers
### Create an Auction
- Click “Create Auction”
- Fill item details
- Upload photos
- Set pricing & duration

### Manage Listings
- Edit auctions before bids
- Replace/delete photos
- Monitor bids
- Cancel or close auctions

### Photo Management
- Supports file paths + Base64
- Auto-add `photo_base64` column if missing

### After Auction Ends
- Receive buyer details
- Contact winner
- Complete transaction
- Receive ratings

---

# ⭐ Rating Sellers
Buyers can:
- Give 1–5 stars  
- Write reviews  
- Edit reviews shortly after posting  

Seller profile displays:
- Average rating  
- Total review count  

---

# 🔧 Configuration

## Email Configuration
Use Gmail SMTP:
```php
$mail->Host = 'smtp.gmail.com';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
```

## Cron Jobs
```bash
* * * * * php /path/to/cron/updateAuctions.php
```

## Security Settings
- Enable HTTPS  
- CSRF tokens  
- Input sanitization  
- Harden PHP sessions  
- Secure file uploads  

---

# 🐛 Troubleshooting

### 500 Internal Server Error
- Check Apache logs  
- Ensure `session_start()` is at top  
- Check permissions  

### Database Connection Failed
- Verify credentials  
- Ensure MySQL running  

### Emails Not Sending
- Verify SMTP  
- Enable app passwords  
- Debug: `$mail->SMTPDebug = 2;`

### Images Not Uploading
```bash
chmod 755 img/uploads/
```
- Check `upload_max_filesize`

### photo_base64 Missing
```sql
ALTER TABLE auctions ADD COLUMN photo_base64 LONGTEXT;
```

---

# 📝 Database Schema (Summary)
- **users**
- **auctions**
- **bids**
- **watchlist**
- **categories**
- **reviews**

---

# 🔐 Security Features
- `password_hash()`  
- Prepared statements  
- XSS protection  
- CSRF tokens  
- Sanitized inputs  
- Secure uploads  

---

# 🚧 Future Enhancements
- [ ] Real-time bidding  
- [ ] Payment gateway  
- [ ] Mobile app  
- [ ] Analytics dashboard  
- [ ] Social login  
- [ ] Multi-language  
- [ ] Public API  
- [ ] Automated tests  
- [ ] Image compression + CDN  
- [ ] Buyer rating system  
- [ ] Dispute resolution  

---

# 🤝 Contributing
```bash
git checkout -b feature/new-feature
git commit -m "Add feature"
git push origin feature/new-feature
```
Open a Pull Request.

---

# 📄 License
MIT License

