from playwright.sync_api import sync_playwright, expect
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Enable console logging
    page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))
    page.on("pageerror", lambda msg: print(f"PAGE ERROR: {msg}"))

    # Load the local HTML file
    # Ensure the path is absolute for Playwright
    cwd = os.getcwd()
    file_path = f"file://{cwd}/verification/mock.html"
    page.goto(file_path)

    # 1. Verify Sticky Bar Elements
    expect(page.locator("#apaf-search-bar")).to_be_visible()
    expect(page.locator("label[for='apaf-op-buy']")).to_have_text("Comprar")
    expect(page.locator("label[for='apaf-op-rent']")).to_have_text("Alugar")
    expect(page.locator("#apaf-btn-advanced")).to_be_visible()
    expect(page.locator("#apaf-btn-search")).to_be_visible()

    # 2. Verify Select2 Initialization (Check if original select is hidden or Select2 container exists)
    expect(page.locator(".select2-container")).to_have_count(3) # City, Bairro, Type

    # 3. Test City Change -> Bairro Enable (Mocked AJAX)
    # Select 'sao-paulo' in City dropdown
    # Select2 handling in Playwright:
    # Click the container to open dropdown
    page.locator("#apaf-cidade + .select2-container").click()
    # Click the option
    page.get_by_role("option", name="São Paulo").click()

    # Wait for Bairro to be enabled (it is disabled initially)
    # The Select2 container usually doesn't have 'disabled' attribute on the top div,
    # but the original select does.
    # Our JS enables the select.
    expect(page.locator("#apaf-bairro")).not_to_be_disabled()

    # 4. Open Modal
    page.click("#apaf-btn-advanced")
    expect(page.locator("#apaf-modal")).to_be_visible()
    # Check if class contains 'is-open'. to_have_class expects exact match or list, but we can check if it contains.
    # Actually Playwright to_have_class supports regex if passed as pattern.
    # But 're=' might be wrong syntax for python playwright version installed?
    # Let's use simple class check if it's the only class or one of them.
    # The element has "apaf-modal is-open".
    expect(page.locator("#apaf-modal")).to_have_class("apaf-modal is-open")

    # 5. Verify Modal Elements
    expect(page.locator("#apaf-price-slider")).to_be_visible()
    expect(page.locator(".apaf-circle-buttons")).to_have_count(3) # Quartos, Banheiros, Vagas

    # 6. Click Circular Button
    page.click(".apaf-circle-buttons[data-target='quartos'] button[data-value='2']")
    expect(page.locator(".apaf-circle-buttons[data-target='quartos'] button[data-value='2']")).to_have_class("active")

    # 7. Close Modal via Close Button
    page.click(".apaf-modal-close")
    expect(page.locator("#apaf-modal")).not_to_be_visible()

    # 8. Perform Search
    page.click("#apaf-btn-search")

    # Wait for results
    # Increase timeout because mock AJAX has delay
    expect(page.locator(".apaf-card-title")).to_contain_text("Imóvel Teste", timeout=10000)

    # Screenshot
    page.screenshot(path="verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
