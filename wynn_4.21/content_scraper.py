# content_scraper.py

import os
import re
import csv
import logging
import time # Import time for delays
from concurrent.futures import ThreadPoolExecutor, as_completed
from tqdm import tqdm
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import WebDriverException, TimeoutException, NoSuchElementException
from selenium_stealth import stealth
from bs4 import BeautifulSoup

import config # Assuming config.py contains credentials and settings

# --- Setup Logging ---
log_formatter = logging.Formatter('%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')
logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO) # Or get level from config

# Clear existing handlers to avoid duplication if run multiple times
if logger.hasHandlers():
    logger.handlers.clear()

stream_handler = logging.StreamHandler()
stream_handler.setFormatter(log_formatter)
logger.addHandler(stream_handler)
# --- End Logging Setup ---


def _sanitize_filename(link):
    """Sanitize a link to create a valid filename."""
    sanitized = re.sub(r'^https?://', '', link)
    sanitized = re.sub(r'[\\/:*?"<>|]+', '_', sanitized)
    sanitized = sanitized.replace('.', '_').replace('/', '_')
    max_len = 200
    if len(sanitized) > max_len:
        sanitized = sanitized[:max_len]
    sanitized = sanitized.strip('_')
    return sanitized


def login_xueqiu(driver):
    """
    Logs into xueqiu.com using credentials from config.
    Attempts to handle verification prompts during login.
    Returns True on success, False on failure or verification block.
    """
    login_url = "https://xueqiu.com/"
    logger.info("Attempting to log into xueqiu.com...")
    try:
        driver.get(login_url)
        time.sleep(2) # Small delay for initial page load

        # --- Try clicking the main login button ---
        try:
            login_button_main = WebDriverWait(driver, 10).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, "a.nav__login__btn")) # Adjust if selector changed
            )
            # Use JS click which can be more resilient
            driver.execute_script("arguments[0].click();", login_button_main)
            logger.info("Clicked main login button.")
            WebDriverWait(driver, 5).until(
                EC.visibility_of_element_located((By.CSS_SELECTOR, ".modal__login")) # Wait for modal
            )
            logger.info("Login modal appeared.")
        except TimeoutException:
            logger.warning("Could not find/click main login button or modal didn't appear quickly. Checking for form elements directly.")
        except Exception as e:
             logger.warning(f"Error clicking main login button: {e}. Proceeding.")

        # --- Interact with login form elements ---
        try:
            username_input = WebDriverWait(driver, 15).until(
                EC.visibility_of_element_located((By.CSS_SELECTOR, ".modal__login input[name='username']"))
            )
            password_input = driver.find_element(By.CSS_SELECTOR, ".modal__login input[name='password']")

            # Use JavaScript to set values
            driver.execute_script("arguments[0].value = '';", username_input)
            driver.execute_script("arguments[0].value = arguments[1];", username_input, config.XUEQIU_USERNAME)
            time.sleep(0.5) # Tiny pause
            driver.execute_script("arguments[0].value = '';", password_input)
            driver.execute_script("arguments[0].value = arguments[1];", password_input, config.XUEQIU_PASSWORD)
            time.sleep(0.5) # Tiny pause

            logger.info("Filled username and password.")

            submit_button = WebDriverWait(driver, 10).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, ".modal__login button[type='submit']"))
            )
            driver.execute_script("arguments[0].click();", submit_button)
            logger.info("Clicked login submit button.")

            # --- Wait for login confirmation OR Verification ---
            # Wait up to 25 seconds for EITHER success OR verification wall
            wait = WebDriverWait(driver, 25)
            try:
                # Check for success element first (adjust selector as needed)
                wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".profile__avatar")))
                logger.info("Login success element found.")

                # Double check: Ensure verification wall isn't present
                time.sleep(1) # Short pause before checking verification
                if "Access Verification" in driver.page_source or "请滑动滑块" in driver.page_source:
                     logger.warning("Login success element found, but verification prompt also detected. Treating as blocked.")
                     # driver.save_screenshot("xueqiu_login_verify_detected_after_success.png")
                     return False

                logger.info("Logged into xueqiu.com successfully.")
                return True

            except TimeoutException:
                 # If timeout occurred, check if it's because of the verification wall
                 time.sleep(1) # Short pause before checking verification
                 page_body_text = driver.find_element(By.TAG_NAME, "body").text
                 if "Access Verification" in page_body_text or "请滑动滑块" in page_body_text:
                     logger.error(f"Xueqiu login blocked by verification challenge. URL: {driver.current_url}")
                     # driver.save_screenshot("xueqiu_login_verification_block.png")
                     return False
                 else:
                     # Other timeout reason (e.g., element never appeared, slow load)
                     logger.error(f"Xueqiu login failed: Timeout waiting for success element or verification. Last URL: {driver.current_url}")
                     # driver.save_screenshot("xueqiu_login_timeout_error.png")
                     return False

        except (TimeoutException, NoSuchElementException) as e:
            logger.error(f"Xueqiu login failed: Could not find or interact with login form elements. Check CSS selectors. URL: {driver.current_url}", exc_info=False)
            # driver.save_screenshot("xueqiu_login_form_error.png")
            return False

    except Exception as e:
        logger.error(f"Xueqiu login failed with unexpected error: {e}", exc_info=True)
        # driver.save_screenshot("xueqiu_login_unexpected_error.png")
        return False


def _extract_text_from_link(link):
    """
    Extract full page text from a link using Chrome with stealth mode.
    Handles Xueqiu login and checks for verification walls.
    Returns the text content or None if an error/block occurs.
    """
    options = webdriver.ChromeOptions()
    # !!! IMPORTANT FOR DEBUGGING: Comment out the next line to run VISIBLY !!!
    options.add_argument('--headless=new')
    # Try adding options to mimic a real browser
    options.add_argument("--window-size=1920,1080")
    options.add_argument('--disable-blink-features=AutomationControlled')
    options.add_argument('--disable-infobars')
    options.add_argument('--disable-extensions')
    options.add_argument('--disable-gpu') # Usually needed for headless
    options.add_argument('--no-sandbox')
    options.add_argument('--ignore-certificate-errors')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--no-proxy-server') # Disable proxy to avoid connection errors
    options.add_argument(f'user-agent={config.USER_AGENT}')

    driver = None
    login_required = "xueqiu.com" in link and not any(domain in ['xueqiu.com', 'xueqiu'] for domain in config.EXCLUDED_DOMAINS)
    login_attempted = False
    login_successful = False
    
    # 根据不同网站设置不同的超时时间
    page_load_timeout = 60  # 默认超时时间
    # 对于某些已知加载较慢的网站，设置更长的超时时间
    if "investing.com" in link:
        page_load_timeout = 120
    elif "yicai.com" in link:
        page_load_timeout = 90

    try:
        driver = webdriver.Chrome(options=options)
        # 设置页面加载超时时间
        driver.set_page_load_timeout(page_load_timeout)
        # Apply stealth settings (make sure it's applied correctly)
        stealth(driver,
                languages=["en-US", "en"],
                vendor="Google Inc.",
                platform="Win32",
                webgl_vendor="Intel Inc.",
                renderer="Intel Iris OpenGL Engine",
                fix_hairline=True,
                user_agent=config.USER_AGENT,
                # run_on_insecure_origins=True, # Careful with this
               )

        if login_required:
            login_attempted = True
            login_successful = login_xueqiu(driver)
            if not login_successful:
                 logger.error(f"Skipping {link} due to login failure or verification block.")
                 return None # Stop processing this link
            else:
                 logger.info(f"Login successful for Xueqiu, pausing briefly before navigation.")
                 time.sleep(3) # Pause after login before getting target page

        # Navigate to the target link
        logger.info(f"Navigating to target link: {link}")
        driver.get(link)

        # Wait for page load (increased timeout)
        WebDriverWait(driver, page_load_timeout).until(
            lambda d: d.execute_script('return document.readyState') == 'complete'
        )
        time.sleep(2) # Extra pause for JS rendering, especially after login/navigation

        # *** Check for Verification Wall AFTER navigation ***
        body_element = WebDriverWait(driver, 15).until(
            EC.presence_of_element_located((By.TAG_NAME, "body"))
        )
        page_text = body_element.text.strip()

        if "Access Verification" in page_text[:500] or "请滑动滑块" in page_text[:500]: # Check beginning of text
             logger.warning(f"Verification challenge detected on target page {link} after navigation. Content blocked.")
             # Uncomment to save source for debugging verification pages
             # try:
             #    debug_filename = f"verification_{_sanitize_filename(link)}.html"
             #    with open(debug_filename, "w", encoding="utf-8") as f_debug:
             #       f_debug.write(driver.page_source)
             #    logger.info(f"Saved verification page source to {debug_filename}")
             # except Exception as e_save:
             #    logger.error(f"Failed to save verification page source: {e_save}")
             return None

        if not page_text and login_required:
             logger.warning(f"Extracted text is empty for {link}. Possible silent failure or dynamic content issue.")
             # Consider saving page source here too for debugging empty pages
             # driver.save_screenshot(f"empty_page_{_sanitize_filename(link)}.png")

        # 需要先安装 pip install beautifulsoup4 lxml
        soup = BeautifulSoup(driver.page_source, 'lxml') # 或者 'html.parser'

        # 尝试定位文章主体容器
        # 这需要根据目标网站的 HTML 结构来调整选择器
        # 常见的模式可能是 <article> 标签，或包含特定 class/id 的 <div>
        article_body = soup.find('article')
        if not article_body:
            article_body = soup.find('div', class_='article-content') # 示例 class
        if not article_body:
            article_body = soup.find('div', id='main-content') # 示例 id
        # ... 可以添加更多尝试

        if article_body:
            # 在找到的文章容器内部，移除已知的非文章部分（评论、广告等）
            comments_section = article_body.find('div', class_='comments-area') # 示例
            if comments_section:
                comments_section.decompose() # 从 HTML 树中移除

            # 移除其他不需要的标签，例如脚本、样式、广告区域等
            for tag in article_body(['script', 'style', 'aside', 'nav', 'header', 'footer', 'iframe', 'form']):
                 tag.decompose()
            for ad_div in article_body.find_all('div', class_=lambda x: x and 'ad' in x.lower()): # 查找包含 'ad' 的 class
                 ad_div.decompose()


            # 提取处理后容器的文本
            article_text = article_body.get_text(separator='\n', strip=True)
        else:
            # 如果找不到明确的文章容器，可以退回到提取 body，但先移除评论等
            logging.warning(f"Could not find specific article container for {link}, attempting cleanup on body.")
            body_tag = soup.body
            if body_tag:
                 # 移除 body 级别的不需要内容
                for tag in body_tag(['script', 'style', 'nav', 'header', 'footer', 'aside', 'iframe', 'form']):
                     tag.decompose()
                comments_section = body_tag.find('div', class_='comments-area') # 示例
                if comments_section:
                     comments_section.decompose()
                # ... 其他移除逻辑 ...
                article_text = body_tag.get_text(separator='\n', strip=True)
            else:
                 article_text = "" # 或者记录错误

        return article_text

    except TimeoutException:
        logger.warning(f"Timeout waiting for page load complete for {link}")
        # if driver: driver.save_screenshot(f"timeout_{_sanitize_filename(link)}.png")
        return None
    except WebDriverException as e:
        # Check if it's a specific navigation error related to verification? Sometimes specific errors are thrown.
        logger.error(f"WebDriver error processing {link}: {str(e)}")
        return None
    except Exception as e:
        logger.error(f"Unexpected error extracting text from {link}: {str(e)}", exc_info=True)
        # if driver: driver.save_screenshot(f"error_{_sanitize_filename(link)}.png")
        return None
    finally:
        if driver:
            driver.quit()


def _process_single_link(link, output_dir):
    """
    Process a single link: extract its text and save it to a file in output_dir.
    Returns a tuple (link, status_message).
    """
    logger.debug(f"Starting processing for: {link}") # Changed level to DEBUG
    text = _extract_text_from_link(link)

    if text:
        safe_filename = _sanitize_filename(link) + ".txt"
        filepath = os.path.join(output_dir, safe_filename)
        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(f"Original URL: {link}\n")
                f.write("-" * 50 + "\n")
                f.write("Content:\n")
                f.write(text)
            logger.info(f"Success: Saved content for {link}") # Changed log level
            return (link, 'success')
        except IOError as e:
            logger.error(f"File writing error for {link} to {filepath}: {str(e)}")
            return (link, f'file error: {str(e)}')
        except Exception as e:
             logger.error(f"Unexpected error saving file for {link}: {str(e)}", exc_info=True)
             return (link, f'unexpected file save error: {str(e)}')
    else:
        # Text extraction failed (error logged in _extract_text_from_link)
        logger.warning(f"Failed: No content extracted or saved for {link}. Reason logged previously.")
        return (link, 'extraction failed')

def scrape_all_websites_content():
    """
    Scrapes content from all websites listed in the filtered links CSV.
    Uses ThreadPoolExecutor for parallel processing.
    """
    input_filename = config.FILTERED_LINKS_CSV
    output_dir = config.EXTRACTED_CONTENT_DIR
    # 减少并发数量，避免过多请求被网站反爬或超时
    max_workers = min(4, config.MAX_WORKERS_CONTENT_SCRAPE) # 限制最大为4个并发
    logger.info(f"Using reduced max_workers: {max_workers} (from config: {config.MAX_WORKERS_CONTENT_SCRAPE}) to improve stability.")
    
    if max_workers > 1 and any("xueqiu.com" in l for l in open(input_filename).read().splitlines() if l):
        logger.warning("Multiple workers configured with Xueqiu links. Login sessions might conflict if run in parallel. Consider setting MAX_WORKERS_CONTENT_SCRAPE = 1 for initial testing.")


    logger.info(f"Starting content scraping from links in {input_filename}...")
    if not os.path.exists(input_filename):
        logger.error(f"Input file not found: {input_filename}. Cannot scrape content.")
        return

    links_to_scrape = []
    try:
        with open(input_filename, "r", encoding='utf-8') as f:
            reader = csv.reader(f)
            try:
                header = next(reader)
                logger.debug(f"CSV Header: {header}")
            except StopIteration:
                 logger.warning(f"Filtered links file {input_filename} is empty.")
                 return
            # 跳过雪球网站的链接，如果它已被排除
            links_to_scrape = [row[0] for row in reader if row and row[0].strip() and 
                             not ("xueqiu.com" in row[0] and any(domain in ['xueqiu.com', 'xueqiu'] for domain in config.EXCLUDED_DOMAINS))]
    except FileNotFoundError:
         logger.error(f"Filtered links file {input_filename} not found during scraping.")
         return
    except Exception as e:
        logger.error(f"Error reading filtered links file {input_filename}: {e}", exc_info=True)
        return

    if not links_to_scrape:
        logger.warning("No valid links found in the filtered file to scrape.")
        return

    os.makedirs(output_dir, exist_ok=True)
    logger.info(f"Saving extracted content to: {output_dir}")
    logger.info(f"Using up to {max_workers} workers for scraping.")

    success_count = 0
    error_count = 0

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_link = {executor.submit(_process_single_link, link, output_dir): link for link in links_to_scrape}

        with tqdm(total=len(links_to_scrape), desc="Scraping Web Content", unit="page") as pbar:
            for future in as_completed(future_to_link):
                link = future_to_link[future]
                try:
                    result_link, status = future.result()
                    if status == 'success':
                        success_count += 1
                    else:
                        error_count += 1
                        # Failure reason already logged, just note the failure here
                        # logger.warning(f"Processing failed for {result_link}: {status}") # Redundant if logged deeper
                except Exception as exc:
                    error_count += 1
                    logger.error(f"Task for link {link} generated an unexpected exception: {exc}", exc_info=True)

                pbar.update(1)
                pbar.set_postfix_str(f"Success: {success_count}, Errors: {error_count}")

    logger.info(f"Content scraping completed. Successful: {success_count}, Failed: {error_count}")


# --- Main Execution Block ---
if __name__ == "__main__":
    try:
        _ = config.FILTERED_LINKS_CSV
        _ = config.EXTRACTED_CONTENT_DIR
        _ = config.MAX_WORKERS_CONTENT_SCRAPE
        # 只有当雪球网站没有被排除时，才检查凭据
        if not any(domain in ['xueqiu.com', 'xueqiu'] for domain in config.EXCLUDED_DOMAINS):
            _ = config.XUEQIU_USERNAME
            _ = config.XUEQIU_PASSWORD
        _ = config.USER_AGENT
        logger.info("Configuration loaded successfully.")
    except AttributeError as e:
        logger.error(f"Configuration error: Missing attribute in config.py - {e}", exc_info=True)
        logger.error("Ensure required variables are defined in config.py")
        exit(1)

    logger.info("Running Content Scraper module directly...")
    if not os.path.exists(config.FILTERED_LINKS_CSV):
         logger.error(f"Input file {config.FILTERED_LINKS_CSV} not found.")
    else:
        # --- Optional: Run cleanup before starting ---
        # import cleanup_utils # Assuming cleanup_utils.py is available
        # logger.info("Running pre-cleanup...")
        # cleanup_utils.run_cleanup()
        # logger.info("Pre-cleanup finished.")
        # --- End Optional Cleanup ---

        scrape_all_websites_content()
    logger.info("Content Scraper module execution finished.")