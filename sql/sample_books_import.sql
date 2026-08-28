  -- ============================================================================
-- Sample Books: Newer Books, Magazines, Research Books, High School, Senior High
-- Import via: mysql -u root -p scan2borrow_2.0 < sample_books_import.sql
-- ============================================================================

-- Create the keywords and book_keywords tables if they don't exist
CREATE TABLE IF NOT EXISTS `keywords` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `book_keywords` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `book_id` INT NOT NULL,
    `keyword_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_book_keyword` (`book_id`, `keyword_id`),
    CONSTRAINT `fk_book_keyword_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_book_keyword_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- NEWER BOOKS (Recent Publications 2020-2024)
-- ============================================================================
INSERT IGNORE INTO `books` (barcode, isbn, title, author, publisher, category, floor_no, section_name, shelf_no, row_no, status)
VALUES
('BK-2001', '9780593419064', 'The Psychology of Money', 'Morgan Housel', 'Harriman House', 'New Books', '2', 'General Section', 'C1', '1', 'Available'),
('BK-2002', '9780593082090', 'Atomic Habits', 'James Clear', 'Avery', 'New Books', '2', 'General Section', 'C1', '2', 'Available'),
('BK-2003', '9780735211292', 'The Midnight Library', 'Matt Haig', 'Viking', 'New Books', '1', 'Fiction Section', 'D1', '1', 'Available'),
('BK-2004', '9780593156608', 'Project Hail Mary', 'Andy Weir', 'Ballantine Books', 'New Books', '2', 'Science Section', 'E1', '1', 'Available'),
('BK-2005', '9780593329191', 'The Alchemist', 'Paulo Coelho', 'HarperOne', 'New Books', '1', 'Fiction Section', 'D1', '2', 'Available'),
('BK-2006', '9780593336051', 'It Ends with Us', 'Colleen Hoover', 'Atria Books', 'New Books', '1', 'Fiction Section', 'D2', '1', 'Available'),
('BK-2007', '9780593336052', 'Verity', 'Colleen Hoover', 'Grand Central Publishing', 'New Books', '1', 'Fiction Section', 'D2', '2', 'Available'),
('BK-2008', '9780593530454', 'Fourth Wing', 'Rebecca Yarros', 'Red Tower Books', 'New Books', '2', 'Fantasy Section', 'F1', '1', 'Available'),
('BK-2009', '9780593530455', 'Iron Flame', 'Rebecca Yarros', 'Red Tower Books', 'New Books', '2', 'Fantasy Section', 'F1', '2', 'Available'),
('BK-2010', '9780593594166', 'The House in the Cerulean Sea', 'TJ Klune', 'Tor Books', 'New Books', '2', 'Fantasy Section', 'F2', '1', 'Available');

-- Keywords for Newer Books
INSERT IGNORE INTO `keywords` (name) VALUES 
('finance'), ('psychology'), ('money'), ('investing'),
('habits'), ('self-improvement'), ('productivity'),
('fiction'), ('philosophy'), ('life choices'),
('science fiction'), ('space'), ('survival'),
('dreams'),
('romance'), ('relationships'),
('thriller'), ('mystery'),
('fantasy'), ('dragons'), ('lgbtq'), ('magical');

-- Link keywords to Newer Books
INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2001' AND k.name IN ('finance', 'psychology', 'money', 'investing');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2002' AND k.name IN ('habits', 'self-improvement', 'productivity');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2003' AND k.name IN ('fiction', 'philosophy', 'life choices');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2004' AND k.name IN ('science fiction', 'space', 'survival');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2005' AND k.name IN ('fiction', 'philosophy', 'dreams');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2006' AND k.name IN ('romance', 'fiction', 'relationships');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2007' AND k.name IN ('thriller', 'romance', 'mystery');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2008' AND k.name IN ('fantasy', 'romance', 'dragons');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2009' AND k.name IN ('fantasy', 'romance', 'dragons');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-2010' AND k.name IN ('fantasy', 'lgbtq', 'magical');

-- ============================================================================
-- MAGAZINE POCKET BOOKS
-- ============================================================================
INSERT IGNORE INTO `books` (barcode, isbn, title, author, publisher, category, floor_no, section_name, shelf_no, row_no, status)
VALUES
('BK-3001', '9781234567890', 'National Geographic - Wonders of the World', 'Various Authors', 'National Geographic Society', 'Magazine Pocket Books', '1', 'Magazine Section', 'M1', '1', 'Available'),
('BK-3002', '9781234567891', 'Time Magazine - Greatest Discoveries', 'Various Authors', 'Time Inc.', 'Magazine Pocket Books', '1', 'Magazine Section', 'M1', '2', 'Available'),
('BK-3003', '9781234567892', 'Reader''s Digest - Best Stories', 'Various Authors', 'Reader''s Digest', 'Magazine Pocket Books', '1', 'Magazine Section', 'M2', '1', 'Available'),
('BK-3004', '9781234567893', 'Scientific American - Mind & Brain', 'Various Authors', 'Scientific American', 'Magazine Pocket Books', '2', 'Science Section', 'S2', '1', 'Available'),
('BK-3005', '9781234567894', 'Popular Science - Future Tech', 'Various Authors', 'Popular Science', 'Magazine Pocket Books', '2', 'Science Section', 'S2', '2', 'Available'),
('BK-3006', '9781234567895', 'Forbes - Business Leaders', 'Various Authors', 'Forbes Media', 'Magazine Pocket Books', '2', 'Business Section', 'B1', '1', 'Available'),
('BK-3007', '9781234567896', 'Sports Illustrated - Greatest Moments', 'Various Authors', 'Sports Illustrated', 'Magazine Pocket Books', '3', 'Sports Section', 'SP1', '1', 'Available'),
('BK-3008', '9781234567897', 'Vogue - Fashion Through the Decades', 'Various Authors', 'Condé Nast', 'Magazine Pocket Books', '1', 'Magazine Section', 'M3', '1', 'Available');

-- Keywords for Magazine Pocket Books
INSERT IGNORE INTO `keywords` (name) VALUES 
('geography'), ('nature'), ('photography'), ('travel'),
('discoveries'), ('history'),
('stories'), ('literature'), ('general'),
('science'), ('psychology'), ('brain'), ('health'),
('technology'), ('future'), ('innovation'),
('business'), ('leadership'), ('success'),
('sports'), ('athletes'),
('fashion'), ('style'), ('culture');

-- Link keywords to Magazine Pocket Books
INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3001' AND k.name IN ('geography', 'nature', 'photography', 'travel');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3002' AND k.name IN ('science', 'discoveries', 'history');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3003' AND k.name IN ('stories', 'literature', 'general');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3004' AND k.name IN ('science', 'psychology', 'brain', 'health');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3005' AND k.name IN ('technology', 'future', 'innovation');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3006' AND k.name IN ('business', 'leadership', 'success');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3007' AND k.name IN ('sports', 'athletes', 'history');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-3008' AND k.name IN ('fashion', 'style', 'culture');

-- ============================================================================
-- RESEARCH BOOKS
-- ============================================================================
INSERT IGNORE INTO `books` (barcode, isbn, title, author, publisher, category, floor_no, section_name, shelf_no, row_no, status)
VALUES
('BK-4001', '9780262033848', 'Research Methods in Education', 'Louis Cohen', 'Routledge', 'Research Books', '3', 'Reference Section', 'R1', '1', 'Available'),
('BK-4002', '9781452257872', 'Doing Qualitative Research', 'David Silverman', 'SAGE Publications', 'Research Books', '3', 'Reference Section', 'R1', '2', 'Available'),
('BK-4003', '9781412972121', 'Survey Research Methods', 'Floyd J. Fowler', 'SAGE Publications', 'Research Books', '3', 'Reference Section', 'R2', '1', 'Available'),
('BK-4004', '9780198754830', 'Statistical Methods for Research', 'Robert G. D. Steel', 'Oxford University Press', 'Research Books', '3', 'Reference Section', 'R2', '2', 'Available'),
('BK-4005', '9788132214103', 'Case Study Research and Applications', 'Robert K. Yin', 'SAGE Publications', 'Research Books', '3', 'Reference Section', 'R3', '1', 'Available'),
('BK-4006', '9780262534786', 'The Craft of Research', 'Wayne C. Booth', 'University of Chicago Press', 'Research Books', '3', 'Reference Section', 'R3', '2', 'Available'),
('BK-4007', '9781446269078', 'How to Write a Thesis', 'Rowena Murray', 'SAGE Publications', 'Research Books', '3', 'Reference Section', 'R4', '1', 'Available'),
('BK-4008', '9780134685991', 'Data Science from Scratch', 'Joel Grus', "O'Reilly Media", 'Research Books', '3', 'Reference Section', 'R4', '2', 'Available');

-- Keywords for Research Books
INSERT IGNORE INTO `keywords` (name) VALUES 
('research'), ('education'), ('methodology'),
('qualitative'), ('methods'),
('survey'), ('quantitative'), ('analysis'),
('statistics'), ('case study'),
('writing'), ('academic'), ('thesis'),
('data science'), ('programming');

-- Link keywords to Research Books
INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4001' AND k.name IN ('research', 'education', 'methodology');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4002' AND k.name IN ('qualitative', 'research', 'methods');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4003' AND k.name IN ('survey', 'quantitative', 'research');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4004' AND k.name IN ('statistics', 'quantitative', 'analysis');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4005' AND k.name IN ('case study', 'qualitative', 'methodology');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4006' AND k.name IN ('research', 'writing', 'academic');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4007' AND k.name IN ('thesis', 'writing', 'academic');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-4008' AND k.name IN ('data science', 'programming', 'analysis');

-- ============================================================================
-- HIGH SCHOOL BOOKS
-- ============================================================================
INSERT IGNORE INTO `books` (barcode, isbn, title, author, publisher, category, floor_no, section_name, shelf_no, row_no, status)
VALUES
('BK-5001', '9780133186126', 'Algebra and Trigonometry', 'Robert F. Blitzer', 'Pearson', 'High School', '1', 'Math Section', 'HS1', '1', 'Available'),
('BK-5002', '9780545582997', 'The Great Gatsby', 'F. Scott Fitzgerald', 'Scholastic', 'High School', '1', 'English Section', 'HS2', '1', 'Available'),
('BK-5003', '9780451524935', '1984', 'George Orwell', 'Signet Classic', 'High School', '1', 'English Section', 'HS2', '2', 'Available'),
('BK-5004', '9780060850524', 'To Kill a Mockingbird', 'Harper Lee', 'HarperCollins', 'High School', '1', 'English Section', 'HS3', '1', 'Available'),
('BK-5005', '9780140283297', 'Of Mice and Men', 'John Steinbeck', 'Penguin Books', 'High School', '1', 'English Section', 'HS3', '2', 'Available'),
('BK-5006', '9780547243653', 'The Giver', 'Lois Lowry', 'Houghton Mifflin', 'High School', '1', 'English Section', 'HS4', '1', 'Available'),
('BK-5007', '9780439023528', 'The Hunger Games', 'Suzanne Collins', 'Scholastic', 'High School', '1', 'English Section', 'HS4', '2', 'Available'),
('BK-5008', '9780547928227', 'The Hobbit', 'J.R.R. Tolkien', 'Houghton Mifflin', 'High School', '1', 'English Section', 'HS5', '1', 'Available'),
('BK-5009', '9780439554930', 'Harry Potter and the Sorcerer''s Stone', 'J.K. Rowling', 'Scholastic', 'High School', '1', 'English Section', 'HS5', '2', 'Available'),
('BK-5010', '9780316769488', 'The Catcher in the Rye', 'J.D. Salinger', 'Little, Brown', 'High School', '1', 'English Section', 'HS6', '1', 'Available'),
('BK-5011', '9780062315009', 'The Fault in Our Stars', 'John Green', 'Dutton Books', 'High School', '1', 'English Section', 'HS6', '2', 'Available'),
('BK-5012', '9780142407332', 'The Outsiders', 'S.E. Hinton', 'Penguin Books', 'High School', '1', 'English Section', 'HS7', '1', 'Available');

-- Keywords for High School Books
INSERT IGNORE INTO `keywords` (name) VALUES 
('algebra'), ('trigonometry'), ('mathematics'),
('literature'), ('fiction'), ('american'),
('dystopia'), ('racism'),
('friendship'),
('young adult'), ('adventure'), ('magic'),
('coming of age'), ('romance');

-- Link keywords to High School Books
INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5001' AND k.name IN ('algebra', 'trigonometry', 'mathematics');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5002' AND k.name IN ('literature', 'fiction', 'american');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5003' AND k.name IN ('literature', 'dystopia', 'fiction');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5004' AND k.name IN ('literature', 'fiction', 'racism');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5005' AND k.name IN ('literature', 'fiction', 'friendship');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5006' AND k.name IN ('literature', 'dystopia', 'young adult');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5007' AND k.name IN ('fiction', 'dystopia', 'young adult');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5008' AND k.name IN ('fantasy', 'adventure', 'literature');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5009' AND k.name IN ('fantasy', 'magic', 'young adult');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5010' AND k.name IN ('literature', 'fiction', 'coming of age');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5011' AND k.name IN ('fiction', 'romance', 'young adult');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-5012' AND k.name IN ('literature', 'fiction', 'coming of age');

-- ============================================================================
-- SENIOR HIGH SCHOOL BOOKS (Grades 11-12)
-- ============================================================================
INSERT IGNORE INTO `books` (barcode, isbn, title, author, publisher, category, floor_no, section_name, shelf_no, row_no, status)
VALUES
('BK-6001', '9780134292380', 'Calculus: Early Transcendentals', 'James Stewart', 'Cengage Learning', 'Senior High School', '1', 'Math Section', 'SHS1', '1', 'Available'),
('BK-6002', '9781118475004', 'Physics for Scientists and Engineers', 'Raymond A. Serway', 'Cengage Learning', 'Senior High School', '2', 'Science Section', 'SHS2', '1', 'Available'),
('BK-6003', '9780134416532', 'Chemistry: The Central Science', 'Theodore E. Brown', 'Pearson', 'Senior High School', '2', 'Science Section', 'SHS2', '2', 'Available'),
('BK-6004', '9781285415652', 'Biology', 'Peter J. Raven', 'Cengage Learning', 'Senior High School', '2', 'Science Section', 'SHS3', '1', 'Available'),
('BK-6005', '9780134292381', 'Statistics for Business and Economics', 'James T. McClave', 'Pearson', 'Senior High School', '1', 'Math Section', 'SHS1', '2', 'Available'),
('BK-6006', '9780134256901', 'Discrete Mathematics and Its Applications', 'Kenneth H. Rosen', 'McGraw-Hill', 'Senior High School', '1', 'Math Section', 'SHS3', '1', 'Available'),
('BK-6007', '9780134444320', 'Fundamentals of Database Systems', 'Ramez Elmasri', 'Pearson', 'Senior High School', '2', 'Computer Section', 'SHS4', '1', 'Available'),
('BK-6008', '9780134685992', 'Introduction to Algorithms', 'Thomas H. Cormen', 'MIT Press', 'Senior High School', '2', 'Computer Section', 'SHS4', '2', 'Available'),
('BK-6009', '9780134685993', 'Computer Systems: A Programmer''s Perspective', 'Randal E. Bryant', 'Prentice Hall', 'Senior High School', '2', 'Computer Section', 'SHS5', '1', 'Available'),
('BK-6010', '9780134685994', 'Clean Code: A Handbook of Agile Software Craftsmanship', 'Robert C. Martin', 'Prentice Hall', 'Senior High School', '2', 'Computer Section', 'SHS5', '2', 'Available'),
('BK-6011', '9780134685995', 'The Pragmatic Programmer', 'David Thomas', 'Addison-Wesley', 'Senior High School', '2', 'Computer Section', 'SHS6', '1', 'Available'),
('BK-6012', '9780134685996', 'Design Patterns: Elements of Reusable Object-Oriented Software', 'Erich Gamma', 'Addison-Wesley', 'Senior High School', '2', 'Computer Section', 'SHS6', '2', 'Available'),
('BK-6013', '9780134685997', 'Introduction to Java Programming', 'Y. Daniel Liang', 'Pearson', 'Senior High School', '2', 'Computer Section', 'SHS7', '1', 'Available'),
('BK-6014', '9780134685998', 'Python Crash Course', 'Eric Matthes', 'No Starch Press', 'Senior High School', '2', 'Computer Section', 'SHS7', '2', 'Available'),
('BK-6015', '9780134685999', 'Web Design with HTML, CSS, JavaScript', 'Jon Duckett', 'Wiley', 'Senior High School', '2', 'Computer Section', 'SHS8', '1', 'Available'),
('BK-6016', '9780134686000', 'Digital Logic and Computer Design', 'M. Morris Mano', 'Pearson', 'Senior High School', '2', 'Computer Section', 'SHS8', '2', 'Available'),
('BK-6017', '9780134686001', 'Networking Essentials', 'Behrouz A. Forouzan', 'McGraw-Hill', 'Senior High School', '2', 'Computer Section', 'SHS9', '1', 'Available'),
('BK-6018', '9780134686002', 'Database Management Systems', 'Raghu Ramakrishnan', 'McGraw-Hill', 'Senior High School', '2', 'Computer Section', 'SHS9', '2', 'Available'),
('BK-6019', '9780134686003', 'Software Engineering: A Practitioner''s Approach', 'Roger S. Pressman', 'McGraw-Hill', 'Senior High School', '2', 'Computer Section', 'SHS10', '1', 'Available'),
('BK-6020', '9780134686004', 'Artificial Intelligence: A Modern Approach', 'Stuart Russell', 'Pearson', 'Senior High School', '2', 'Computer Section', 'SHS10', '2', 'Available');

-- Keywords for Senior High School Books
INSERT IGNORE INTO `keywords` (name) VALUES 
('calculus'), ('advanced math'),
('physics'), ('science'), ('engineering'),
('chemistry'), ('laboratory'),
('biology'), ('life science'), ('organisms'),
('business'), ('economics'),
('discrete math'), ('computer science'), ('logic'),
('database'), ('programming'), ('algorithms'),
('computer systems'), ('architecture'),
('software'), ('best practices'), ('software development'), ('career'),
('design patterns'), ('object-oriented'),
('java'), ('python'), ('beginner'),
('web design'), ('html'), ('css'), ('javascript'),
('digital logic'), ('computer design'), ('hardware'),
('networking'), ('computers'), ('technology'),
('management systems'), ('sql'),
('software engineering'), ('development'), ('methodology'),
('artificial intelligence'), ('ai'), ('machine learning');

-- Link keywords to Senior High School Books
INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6001' AND k.name IN ('calculus', 'advanced math', 'mathematics');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6002' AND k.name IN ('physics', 'science', 'engineering');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6003' AND k.name IN ('chemistry', 'science', 'laboratory');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6004' AND k.name IN ('biology', 'life science', 'organisms');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6005' AND k.name IN ('statistics', 'business', 'economics');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6006' AND k.name IN ('discrete math', 'computer science', 'logic');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6007' AND k.name IN ('database', 'computer science', 'programming');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6008' AND k.name IN ('algorithms', 'computer science', 'programming');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6009' AND k.name IN ('computer systems', 'programming', 'architecture');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6010' AND k.name IN ('programming', 'software', 'best practices');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6011' AND k.name IN ('programming', 'software development', 'career');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6012' AND k.name IN ('design patterns', 'software', 'object-oriented');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6013' AND k.name IN ('java', 'programming', 'computer science');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6014' AND k.name IN ('python', 'programming', 'beginner');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6015' AND k.name IN ('web design', 'html', 'css', 'javascript');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6016' AND k.name IN ('digital logic', 'computer design', 'hardware');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6017' AND k.name IN ('networking', 'computers', 'technology');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6018' AND k.name IN ('database', 'management systems', 'sql');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6019' AND k.name IN ('software engineering', 'development', 'methodology');

INSERT IGNORE INTO `book_keywords` (book_id, keyword_id)
SELECT b.id, k.id FROM books b, keywords k
WHERE b.barcode = 'BK-6020' AND k.name IN ('artificial intelligence', 'ai', 'machine learning');

-- ============================================================================
-- Summary
-- ============================================================================
-- Total books in this file: 48
-- Newer Books: 10
-- Magazine Pocket Books: 8
-- Research Books: 8
-- High School Books: 12
-- Senior High School Books: 20
-- Note: INSERT IGNORE is used, so existing books will be skipped automatically
-- ============================================================================