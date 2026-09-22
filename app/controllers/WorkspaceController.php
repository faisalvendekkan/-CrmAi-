<?php
declare(strict_types=1);

final class WorkspaceController
{
    public function index(): void
    {
        $u = Auth::require("workspace_items");

        $kind = trim((string) ($_GET["kind"] ?? "all"));
        if (!in_array($kind, ["all", "prompt", "note"], true)) {
            $kind = "all";
        }

        $q = trim((string) ($_GET["q"] ?? ""));
        $sql = "SELECT * FROM workspace_items WHERE user_id = ?";
        $params = [(int) $u["id"]];

        if ($kind !== "all") {
            $sql .= " AND kind = ?";
            $params[] = $kind;
        }

        if ($q !== "") {
            $sql .= " AND (title LIKE ? OR category LIKE ? OR content LIKE ?)";
            $like = "%" . $q . "%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY is_pinned DESC, COALESCE(updated_at, created_at) DESC, id DESC";

        $edit = null;
        $editId = isset($_GET["edit"]) ? (int) $_GET["edit"] : 0;
        if ($editId > 0) {
            $edit = DB::one(
                "SELECT * FROM workspace_items WHERE id = ? AND user_id = ?",
                [$editId, (int) $u["id"]]
            );
        }

        view("workspace/index", [
            "title" => "Prompts & Notes",
            "items" => DB::all($sql, $params),
            "kind" => $kind,
            "q" => $q,
            "edit" => $edit,
            "canEdit" => can("workspace_items", "edit"),
            "pageModule" => "workspace",
        ]);
    }

    public function save(): void
    {
        $u = Auth::require("workspace_items", "edit");

        $id = (int) input("id", "0");
        $kind = input("kind") === "prompt" ? "prompt" : "note";
        $title = mb_substr(trim(input("title")), 0, 180);
        $category = mb_substr(trim(input("category")), 0, 100);
        $content = trim(input("content"));
        $isPinned = input("is_pinned") === "1" ? 1 : 0;

        if ($title === "" || $content === "") {
            flash("error", "Title and content are required.");
            redirect("workspace");
        }

        $data = [
            "kind" => $kind,
            "title" => $title,
            "category" => $category,
            "content" => $content,
            "is_pinned" => $isPinned,
        ];

        if ($id > 0) {
            $owned = DB::one(
                "SELECT id FROM workspace_items WHERE id = ? AND user_id = ?",
                [$id, (int) $u["id"]]
            );
            if (!$owned) {
                abort(404);
            }
            DB::update("workspace_items", $data, $id);
        } else {
            $data["user_id"] = (int) $u["id"];
            $id = DB::insert("workspace_items", $data);
        }

        Activity::log("saved", "workspace", $id, ucfirst($kind) . ": " . $title);
        flash("success", ucfirst($kind) . " saved.");
        redirect("workspace");
    }

    public function pin(int $id): void
    {
        $u = Auth::require("workspace_items", "edit");
        $item = DB::one(
            "SELECT * FROM workspace_items WHERE id = ? AND user_id = ?",
            [$id, (int) $u["id"]]
        );

        if (!$item) {
            abort(404);
        }

        $newValue = (int) $item["is_pinned"] === 1 ? 0 : 1;
        DB::update("workspace_items", ["is_pinned" => $newValue], $id);

        flash("success", $newValue ? "Pinned to top." : "Unpinned.");
        redirect("workspace");
    }

    public function delete(int $id): void
    {
        $u = Auth::require("workspace_items", "edit");
        $item = DB::one(
            "SELECT * FROM workspace_items WHERE id = ? AND user_id = ?",
            [$id, (int) $u["id"]]
        );

        if (!$item) {
            abort(404);
        }

        DB::delete("workspace_items", $id);
        Activity::log("deleted", "workspace", $id, ucfirst((string) $item["kind"]) . ": " . $item["title"]);

        flash("success", "Item deleted.");
        redirect("workspace");
    }
}
