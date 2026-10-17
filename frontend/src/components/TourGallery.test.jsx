import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import { TourGallery } from "./TourGallery";

vi.mock("./LazyImage", () => ({
  LazyImage: ({ src, alt, className }) => (
    <img src={src} alt={alt} className={className} data-testid="lazy-img" />
  ),
}));

vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

describe("TourGallery", () => {
  const images = ["https://example.com/1.jpg", "https://example.com/2.jpg"];

  it("renders thumbnail for each image", () => {
    render(<TourGallery images={images} />);
    const thumbnails = screen.getAllByRole("button", { name: "tours.gallery.viewPhoto" });
    expect(thumbnails).toHaveLength(2);
  });

  it("shows loading placeholder when no images", () => {
    render(<TourGallery images={[]} />);
    expect(screen.getByText("tours.gallery.empty")).toBeInTheDocument();
  });

  it("opens lightbox on main image click", () => {
    render(<TourGallery images={images} />);
    const openButton = screen.getByRole("button", { name: "tours.gallery.open" });
    fireEvent.click(openButton);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("closes lightbox on close button", () => {
    render(<TourGallery images={images} />);
    const openButton = screen.getByRole("button", { name: "tours.gallery.open" });
    fireEvent.click(openButton);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    const closeButton = screen.getByRole("button", { name: "tours.gallery.close" });
    fireEvent.click(closeButton);
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("navigates with prev/next buttons", () => {
    render(<TourGallery images={images} />);
    const thumbs = screen.getAllByRole("button", { name: "tours.gallery.viewPhoto" });
    fireEvent.click(thumbs[1]);
    expect(screen.getByRole("dialog")).toHaveAttribute(
      "aria-label",
      "tours.gallery.photoOf"
    );
    const nextButtons = screen.getAllByRole("button", { name: "tours.gallery.next" });
    expect(nextButtons).not.toHaveLength(0);
    nextButtons.forEach((b) => expect(b).toBeDisabled());
    const prevButtons = screen.getAllByRole("button", { name: "tours.gallery.prev" });
    fireEvent.click(prevButtons[0]);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("handles ESC key", () => {
    render(<TourGallery images={images} />);
    const openButton = screen.getByRole("button", { name: "tours.gallery.open" });
    fireEvent.click(openButton);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    fireEvent.keyDown(document, { key: "Escape" });
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
