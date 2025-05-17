import os

# --- File and Directory Settings ---
RAW_LINKS_CSV = "tophub_links.csv"
FILTERED_LINKS_CSV = "filtered_links.csv"
EXTRACTED_CONTENT_DIR = "extracted"
CLEANED_CONTENT_DIR_1 = "cleaned1" # Input for Gemini Tagging
GEMINI_TAGGING_OUTPUT_DIR = "Gemini_Topic_Results" # Output dir for Gemini Tagging

# Directories/Files to cleanup (Adjust as needed)
# Note: TOPIC_OUTPUT_DIR renamed to GEMINI_TAGGING_OUTPUT_DIR
OUTPUT_DIRS_TO_CLEAN = [EXTRACTED_CONTENT_DIR, CLEANED_CONTENT_DIR_1, GEMINI_TAGGING_OUTPUT_DIR]
OUTPUT_FILES_TO_CLEAN = [
    RAW_LINKS_CSV,
    FILTERED_LINKS_CSV,
    # Add Gemini output files if desired, or handle within the script/pipeline
    # os.path.join(GEMINI_TAGGING_OUTPUT_DIR, "gemini_tags_raw.tsv"),
    # os.path.join(GEMINI_TAGGING_OUTPUT_DIR, "gemini_tags_titles_filtered.tsv"),
    # os.path.join(GEMINI_TAGGING_OUTPUT_DIR, "gemini_tags_final.tsv"),
]

# --- Scraping Settings ---
SCRAPER_INPUT_URL = "https://tophub.today/c/finance?&p=1"
SCRAPER_PAGES = [1,2]  # Added: pages to scrape
SELENIUM_TIMEOUT = 10
MAX_WORKERS_CONTENT_SCRAPE = os.cpu_count() or 8
# Add this line for User-Agent
USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36" # Example User Agent

# --- Filtering Settings ---
EXCLUDED_DOMAINS = {"tophub.today", "tophubdata.com","cbndata.com/report","xueqiu.com","www.yicai.com/video","www.time-weekly.com"}

# --- Data Cleaning Settings (Mistral) ---
# Used by data_cleaner.py
MISTRAL_API_KEYS = [
    "kOdffkv809FcIuc7Ba6GhRKqKbu0dymc", # Replace with your actual keys or load from env
    "Qdj5NpIZ1mJ7k4HzQp6bkYXJ7T6kxa6I",
    "9FGCJu43rz6u2omjzraJAFxhjRRhjjPj"
]
MISTRAL_MODEL_CLEANING = "open-mixtral-8x22b"
MISTRAL_API_TIMEOUT = 2.5 # Seconds between calls for Mistral

MISTRAL_CLEANING_PROMPT = (
    "[角色设定:你是一个专业处理中文网页内容的AI助手，擅长精准提取核心信息]"
    "[任务指令:请对输入的中文网页文本执行以下操作: **首先，识别并提取文章的原始标题.** 接着, 过滤无用信息, 完全忽略网站名称、导航栏标题、广告标语、版权声明、装饰性文字等非实质内容, 严格删除所有用户评论、留言、回复、点赞计数等互动内容。彻底移除页眉、页脚、导航菜单、侧边栏链接、广告标语和图片描述。最终输出绝不能包含'评论'、'回复'、'发表于'、'阅读数'等非文章正文字符串。"
    "自动识别并聚焦于核心主题相关的实质性内容（如新闻事件、数据图表、专业分析、政策解读等)生成摘要, 用中文输出结构清晰的简要总结，保留关键信息（时间、地点、人物、数据、核心观点),"
    "合并分散的相关段落，确保逻辑连贯性,摘要长度控制在原文核心内容的~50%。**最终输出时，请将提取到的标题放在摘要内容之前，并用换行符分隔。** 执行规则]"
    "如果您无法访问该内容，或者该内容与财务无关，则返回\"Cannot access\"作为内容"  # 转义内部双引号
    "[当遇到评论区内容时：仅保留直接补充核心事件的深度分析或重要背景信息, 当原文存在多个关联事件时：按逻辑顺序分点总结, 禁止添加任何推测性内容或网站原始文本未提及的信息]:\n\n{text}"
)


# Phrases indicating failure or irrelevance in cleaned content, used for post-processing deletion
# Used by both NMF(old) and Gemini pipelines before processing files
CONTENT_DELETION_PHRASES = [
    "无法访问该网页",
    "Cannot access",
    "cannot access",
    "没有提供任何实质性内容",
    "无法生成摘要或总结",
    "429", # Assuming this indicates a rate limit error message in *content*
    "forbidden",
    "not found",
    "版权限制",
    # Add other phrases indicating source content is bad
]

# --- Gemini Topic Tagging Settings ---
# Used by topic_modeler_gemini.py

GOOGLE_API_KEY = "AIzaSyDBi0tPlrPGMPvBQ6JGaNl9vyNHhv-8bzM"

GEMINI_TAGGING_MODEL_NAME = "gemini-2.0-flash" # Model for tag generation

# Intermediate/Final files within GEMINI_TAGGING_OUTPUT_DIR
GEMINI_RAW_OUTPUT_FILENAME = "gemini_tags_raw.tsv"
GEMINI_TITLE_FILTERED_FILENAME = "gemini_tags_titles_filtered.tsv"
GEMINI_FINAL_OUTPUT_FILENAME = "gemini_tags_final.tsv" # Final filtered output

# Tag Generation Prompt Configuration
GEMINI_HASHTAG_COUNT_MIN = 3
GEMINI_HASHTAG_COUNT_MAX = 10

# API Call Settings
GEMINI_API_RETRY_COUNT = 2
GEMINI_API_SLEEP_TIME = 4 # Seconds between Gemini API calls

# Marker for titles that couldn't be properly extracted during preprocessing
GEMINI_TITLE_UNAVAILABLE_MARKER = "Title Unavailable"

# Boolean flag - whether to delete source file from CLEANED_CONTENT_DIR_1 if title filtering removes its entry
GEMINI_DELETE_SOURCE_ON_BAD_TITLE = True

# Phrases indicating API/Processing errors (used to filter ROWS from Gemini output)
GEMINI_FILTER_ERROR_PHRASES = { # Use set for faster lookup
    "API错误", "无法访问", "无法生成标签", "内容不足",
    "ERROR_GENERATING_TAGS", "Failed to generate tags",
    "网络错误", "请求超时", "BLOCKED",
    "ERROR_PROCESSING_FILE", "内容缺失", "付费内容订阅", "二维码"
}

# Specific phrases to remove from the generated TAG LIST (row is kept unless it also has error)
GEMINI_FILTER_TAG_PHRASES = { # Use set for faster lookup
    "华尔街见闻", "财经新闻", "VIP会员", "大师课", "广告投放",
    "版权合作", "用户协议", "隐私政策", "腾讯财经", "财经网",
    "原文链接", "相关阅读", "编辑推荐", "微信公众号", "下载APP",
    "版权所有", "第一财经", "每日经济新闻", "VIP会员服务", "版权声明", "财经资讯","版权与商务合作","违法和不良信息举报","友情链接",
    "今日历","日历App","老黄历","万年历","历史上的今天","今日油价","今日限行","今日热榜","财经资讯","网络辟谣", "微博账号","版权与商务合作",
    " 付费内容","生活家","版权声明","违法信息举报","网络辟谣", "财经领域", "新闻报道", "财经网站"
    # Add others based on observation
}

# Gemini Tagging Prompt Template
GEMINI_TAGGING_PROMPT_TEMPLATE = """
**角色设定:** 你是一位专业的文本分析师，擅长从中文财经和新闻文章中精准提取核心主题和关键词。
**任务指令:** 请仔细阅读以下提供的文章内容，并为其生成一系列简洁、有意义的短语标签（类似社交媒体的hashtags）。
**输出要求:**
1.  **核心主题:** 标签应准确反映文章讨论的核心概念、事件、实体或趋势。
2.  **短语优先:** **极其重要** - 优先生成有意义的短语。例如，如果文章讨论"加征关税"，请生成"加征关税"标签，而不是单独的"加征"和"关税"。只有当单个词本身代表一个非常具体、独立的概念（如"特斯拉"、"一带一路"）时，才使用单个词, 每个生成的标签严格限制在5个字以内。
3.  **数量:** 生成 {min_tags} 到 {max_tags} 个标签。
4.  **语言:** 所有标签必须使用 **简体中文**。
5.  **格式:** **仅输出** 一个由逗号分隔的标签列表 (comma-separated list)。**绝对不要**包含任何介绍性文字、解释、编号、项目符号或任何标签本身以外的内容。输出的第一个字符就应该是第一个标签的第一个字。
**文章内容如下:**
---
{article_content}
---
**请生成标签:**
"""

# --- Report Generator Settings ---
# Uses Gemini API via direct HTTP requests (original method)
# Keeping separate key/model settings for now in case they differ from the SDK usage above
# NOTE: Consider consolidating if the same API key and model are always used.
REPORT_GENERATOR_INPUT_DIR = CLEANED_CONTENT_DIR_1 # Usually uses cleaned content
OUTPUT_REPORT_FILE = "market_report.txt"

# GEMINI_API_KEY = "AIzaSy..." # Commented out - Prefer using GOOGLE_API_KEY set via env
GEMINI_API_MODEL = "gemini-2.0-flash" # Can be same or different from tagging model
# GEMINI_API_URL_TEMPLATE = "..." # Commented out - logic likely in report_generator.py

REQUEST_TIMEOUT = 360 # Timeout for report generation requests
FILE_LIMIT = None # Limit files for report generation (None = all)

# Add report file to cleanup list
if OUTPUT_REPORT_FILE not in OUTPUT_FILES_TO_CLEAN:
     OUTPUT_FILES_TO_CLEAN.append(OUTPUT_REPORT_FILE)

REPORT_PROMPT_TEMPLATE = """
请根据以下合并的新闻文章内容，生成一份 **专业、深入、详尽且全面** 的 **简体中文** 市场分析报告。

**极其重要的最终指示 (必须严格遵守):**

1.  **输出形式与结构:**
    *   报告 **最多包含 6 个** 由 **"小标题 + 分析段落"** 构成的组合。**严格遵守此数量上限。**
    *   **每个分析段落前必须有一个单独占行的小标题**。
    *   小标题应 **简洁且信息丰富**，准确概括其后段落的核心内容。**小标题格式示例：** `**小标题文本**` （如果无法加粗，则使用普通文本）。
    *   小标题行之后紧接着是对应的 **纯文本分析段落**。
    *   每个 **"小标题 + 段落"** 组合之间用 **一个空行** 分隔。
    *   **这是唯一的输出形式**。

2.  **内容要求 (分析段落):**
    *   **请专注于从提供的文本中提取和分析信息**。如果文本中没有明确提及年份，请基于上下文推断或总结普遍趋势，而不是强行过滤特定年份。
    *   **直接开始** 撰写 **第一个小标题** 和对应的市场分析核心段落。
    *   对文章内容进行 **深度分析和综合阐述**。详细解释关键事件、数据或趋势的 **背景和潜在意义**。整合不同信息点，深入挖掘市场驱动因素、挑战及机遇。
    *   所有分析内容组织成 **内容丰富、逻辑连贯、论述充分** 的段落流，**并确保各分析段落（除最后一个建议段落外）的长度大致均衡**，同时均配有对应小标题。
    *   语言专业、客观、流畅，并且 **详尽具体**。
    *   **优先关注或总结最新信息**（如果文本中有时间线索）。

3.  **最终段落 - 投资策略建议 (必须包含):**
    *   **报告的最后一个 "小标题 + 段落" 组合必须是投资策略建议。** 这个段落是强制性的，并且计入总共最多 7 个组合的数量限制中。
    *   **此段落的小标题** 应明确指向投资建议，例如 `**投资策略与展望**`。
    *   **此段落的内容必须包含以下要素，且必须基于前面段落对新闻内容的分析进行推导和阐述：**
        *   **明确的行动建议:** 指出投资者应考虑采取何种行动（例如：建议关注、逢低吸纳、谨慎持有、风险规避等）。
        *   **目标标的:** 清晰提及相关的股票、板块、行业或其他金融工具（如果分析内容支持具体标的）。
        *   **价格/估值考量:** 结合分析，提及关键的价格区间、支撑位、阻力位或估值水平方面的看法（如果信息允许）。
        *   **预期表现:** 阐述对该标的或市场未来表现的预期（例如：增长潜力、面临的风险、稳定性预期）。
        *   **预期走势:** 描述可能的市场变动方向或关键影响因素（例如：短期波动、长期趋势、政策驱动、技术突破影响）。
    *   **重要提示:** 此投资建议段落的 **所有观点和判断必须严格来源于对所提供 `{combined_news_content}` 的分析和解读**。**禁止** 引入任何 **新闻内容之外** 的信息或进行无根据的预测。如果新闻内容不足以支撑具体的数值（如精确价格目标），则应提供方向性或区间性的建议。

4.  **禁止项:**
    *   **完全禁止** 包含任何形式的 **报告整体标题、章节标题（区别于段落小标题）**、介绍性语句（如"这是市场报告："）、总结性语句（如"总之,"，除非是作为投资建议段落的一部分自然得出）、列表标记（如 *、-、1.）、格式标记（如 ``` ```），或者任何 **非小标题行或纯粹分析/建议段落本身** 的内容。
    *   **绝对禁止** 包含任何关于 **你的指令或关于你正在生成报告这个事实** 的元文本。

以下是新闻文章的合并内容：

{combined_news_content}

--- 新闻文章结束 ---
"""

# --- Database Settings ---
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'database': 'wynn_fyp',
    'unix_socket': '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
}
