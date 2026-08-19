-- Sample starter data — safe to edit or delete rows via the admin dashboard afterward.
SET NAMES utf8mb4;

INSERT INTO business_settings (business_name, business_email, business_phone, business_address, hours_note, booking_notice)
VALUES (
  'Diamond Nails & Spa',
  'hello@diamondnailslayton.com',
  '(801) 547-1107',
  '709 N Main St, Layton, UT 84041',
  'Tue–Sat 9:30am–7pm · Sun 11am–4pm · Mon Closed',
  'Please arrive 10 minutes early. We hold reservations for 15 minutes past your scheduled time.'
);

INSERT INTO services (name, slug, description, duration_minutes, price, image_url, category, is_active, sort_order) VALUES
('Classic Manicure', 'classic-manicure', 'Nail shaping, cuticle care, hand massage, and a flawless polish finish in the shade of your choice.', 30, 32.00, 'https://images.pexels.com/photos/7755296/pexels-photo-7755296.jpeg?auto=compress&cs=tinysrgb&w=900', 'Manicure', 1, 1),
('Gel Manicure', 'gel-manicure', 'Our signature long-wear gel polish over a refined manicure — glossy, chip-resistant shine for weeks.', 45, 45.00, 'https://images.pexels.com/photos/34885842/pexels-photo-34885842.jpeg?auto=compress&cs=tinysrgb&w=900', 'Manicure', 1, 2),
('Classic Pedicure', 'classic-pedicure', 'A relaxing soak, exfoliation, callus care, and massage finished with a smooth polish application.', 45, 42.00, 'https://images.pexels.com/photos/34930123/pexels-photo-34930123.jpeg?auto=compress&cs=tinysrgb&w=900', 'Pedicure', 1, 3),
('Gel Pedicure', 'gel-pedicure', 'The full classic pedicure ritual, sealed with a durable gel polish for a lasting, mirror-like shine.', 55, 55.00, 'https://images.pexels.com/photos/18441299/pexels-photo-18441299.jpeg?auto=compress&cs=tinysrgb&w=900', 'Pedicure', 1, 4),
('Nail Art Design', 'nail-art-design', 'Hand-painted detail, fine linework, or accent embellishments layered over any manicure or pedicure.', 30, 20.00, 'https://images.pexels.com/photos/34835286/pexels-photo-34835286.jpeg?auto=compress&cs=tinysrgb&w=900', 'Nail Art', 1, 5),
('Acrylic Full Set', 'acrylic-full-set', 'Sculpted acrylic extensions built to your preferred length and shape, finished with polish or gel color.', 75, 65.00, 'https://images.pexels.com/photos/6135680/pexels-photo-6135680.jpeg?auto=compress&cs=tinysrgb&w=900', 'Enhancements', 1, 6);

INSERT INTO staff (full_name, title, photo_url, bio, color_hex, is_active, sort_order) VALUES
('Maria Gomez', 'Senior Nail Technician', 'https://images.pexels.com/photos/18090215/pexels-photo-18090215.jpeg?auto=compress&cs=tinysrgb&w=400', '12+ years of experience specializing in gel application and precision nail art. Maria trained under master technicians before joining Diamond Nails.', '#b8836a', 1, 1),
('Wendy Tran', 'Nail Technician', 'https://images.pexels.com/photos/5128112/pexels-photo-5128112.jpeg?auto=compress&cs=tinysrgb&w=400', 'Known for her steady hand and flawless acrylic sets, Wendy has been part of the Diamond Nails team since it opened.', '#6e4457', 1, 2),
('Amy Nguyen', 'Nail Technician', 'https://images.pexels.com/photos/17909398/pexels-photo-17909398.jpeg?auto=compress&cs=tinysrgb&w=400', 'Amy brings a gentle touch and an eye for detail to every pedicure and manicure, with a special love for seasonal nail art.', '#3c6e9c', 1, 3),
('Michael Reyes', 'Nail Technician', 'https://images.pexels.com/photos/8468132/pexels-photo-8468132.jpeg?auto=compress&cs=tinysrgb&w=400', 'Michael specializes in acrylic and dip powder work, bringing a calm, easygoing energy to every appointment.', '#4c7a4f', 1, 4);

-- No admin_users row is seeded here on purpose: a bcrypt hash needs to be generated
-- by PHP's own password_hash() so it is guaranteed to verify correctly.
-- After importing this file, visit /admin/setup.php once on your live site to create
-- the first admin login — that script hashes the password server-side and then
-- disables itself.
