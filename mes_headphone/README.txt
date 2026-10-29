HƯỚNG DẪN CÀI ĐẶT - MES TAI NGHE
1. Cài XAMPP (https://www.apachefriends.org), cài đặt mặc định.
2. Mở XAMPP Control Panel -> Start "Apache" và "MySQL".
3. Giải nén thư mục mes_headphone vào C:\xampp\htdocs\  (kết quả: C:\xampp\htdocs\mes_headphone\index.php)
4. Mở http://localhost/phpmyadmin -> tab "Import" -> chọn database/mes_headphone.sql -> bấm "Import"
   (file tự tạo database mes_headphone, các bảng và dữ liệu mẫu).
5. Truy cập http://localhost/mes_headphone/
6. Đăng nhập:  admin / admin123   (toàn quyền)   |   worker / 123456  (chỉ xem + cập nhật sản lượng + QC)
7. Nếu MySQL có mật khẩu root, sửa config/database.php.
