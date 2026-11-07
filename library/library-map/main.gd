extends Node3D

const API_URL := "http://localhost/000CAPSTONE/map_api.php"
const LIBRARY_MODEL := "res://library.glb"

var library: Node3D
var camera: Camera3D
var entrance: Node3D
var shelves := {}              # shelf name (String) -> MeshInstance3D
var path_root: Node3D
var bounce_arrow: MeshInstance3D
var bounce_base_y := 0.0
var results := []

var search_box: LineEdit
var result_list: ItemList
var info_label: Label


func _ready() -> void:
	_build_world()
	_build_ui()
	await _load_genres()


func _process(_delta: float) -> void:
	# bouncing arrow above the chosen shelf
	if is_instance_valid(bounce_arrow):
		bounce_arrow.position.y = bounce_base_y + sin(Time.get_ticks_msec() / 200.0) * 0.3


# ---------------------------------------------------------------- world
func _build_world() -> void:
	var env := Environment.new()
	env.background_mode = Environment.BG_COLOR
	env.background_color = Color(0.85, 0.9, 0.95)
	env.ambient_light_source = Environment.AMBIENT_SOURCE_COLOR
	env.ambient_light_color = Color.WHITE
	env.ambient_light_energy = 0.45
	var we := WorldEnvironment.new()
	we.environment = env
	add_child(we)

	var sun := DirectionalLight3D.new()
	sun.rotation_degrees = Vector3(-55, 30, 0)
	sun.light_energy = 0.7
	sun.light_specular = 0.0
	add_child(sun)

	library = (load(LIBRARY_MODEL) as PackedScene).instantiate()
	add_child(library)
	entrance = library.find_child("Entrance", true, false)

	# make every Shelf_* clickable
	for node in library.find_children("Shelf_*", "MeshInstance3D", true, false):
		node.create_trimesh_collision()
		shelves[str(node.name)] = node

	camera = Camera3D.new()
	add_child(camera)
	camera.look_at_from_position(Vector3(0, 16, 9), Vector3.ZERO)

	path_root = Node3D.new()
	add_child(path_root)


# ---------------------------------------------------------------- UI
func _build_ui() -> void:
	var layer := CanvasLayer.new()
	add_child(layer)

	var panel := PanelContainer.new()
	panel.position = Vector2(12, 12)
	panel.custom_minimum_size = Vector2(320, 0)
	layer.add_child(panel)

	var box := VBoxContainer.new()
	panel.add_child(box)

	var title := Label.new()
	title.text = "Library Map"
	box.add_child(title)

	var row := HBoxContainer.new()
	box.add_child(row)

	search_box = LineEdit.new()
	search_box.placeholder_text = "Search book, author or genre..."
	search_box.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	search_box.text_submitted.connect(func(_t): _on_search())
	row.add_child(search_box)

	var btn := Button.new()
	btn.text = "Search"
	btn.pressed.connect(_on_search)
	row.add_child(btn)

	result_list = ItemList.new()
	result_list.custom_minimum_size = Vector2(0, 110)
	result_list.item_selected.connect(_on_result_selected)
	box.add_child(result_list)

	info_label = Label.new()
	info_label.text = "Click a shelf or search for a book."
	info_label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	box.add_child(info_label)


# ---------------------------------------------------------------- database
func _api(query: String) -> Variant:
	var http := HTTPRequest.new()
	add_child(http)
	if http.request(API_URL + "?" + query) != OK:
		http.queue_free()
		return null
	var response = await http.request_completed
	http.queue_free()
	if response[0] != HTTPRequest.RESULT_SUCCESS or response[1] != 200:
		return null
	return JSON.parse_string(response[3].get_string_from_utf8())


func _load_genres() -> void:
	var genres = await _api("action=genres")
	if genres == null:
		info_label.text = "Cannot reach the database. Is XAMPP (Apache + MySQL) running?"
		return

	# put the genre name from the database above each shelf
	for g in genres:
		var shelf: Node3D = shelves.get(g["shelf_name"])
		if shelf == null:
			continue
		var label := Label3D.new()
		label.text = g["name"]
		label.font_size = 64
		label.pixel_size = 0.01
		label.modulate = Color(0.1, 0.1, 0.1)
		label.billboard = BaseMaterial3D.BILLBOARD_ENABLED
		label.no_depth_test = true
		label.position = Vector3(0, shelf.get_aabb().end.y + 0.6, 0)
		shelf.add_child(label)


# ---------------------------------------------------------------- clicking a shelf
func _unhandled_input(event: InputEvent) -> void:
	if event is InputEventMouseButton and event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
		var from := camera.project_ray_origin(event.position)
		var to := from + camera.project_ray_normal(event.position) * 100.0
		var query := PhysicsRayQueryParameters3D.create(from, to)
		var hit := get_world_3d().direct_space_state.intersect_ray(query)
		if hit.is_empty():
			return
		var shelf := (hit["collider"] as Node).get_parent()
		if shelf != null and str(shelf.name).begins_with("Shelf_"):
			_on_shelf_clicked(str(shelf.name))


func _on_shelf_clicked(shelf_name: String) -> void:
	_show_route(shelf_name)
	result_list.clear()
	results = []

	var data = await _api("action=shelf&name=" + shelf_name.uri_encode())
	if data == null or data.is_empty():
		info_label.text = "No genre is linked to " + shelf_name + " in the database."
		return

	var text := "Genre: %s\nBooks:" % data["genre"]
	for b in data["books"]:
		text += "\n- %s (%s)" % [b["title"], b["author"]]
	info_label.text = text


# ---------------------------------------------------------------- searching
func _on_search() -> void:
	var q := search_box.text.strip_edges()
	if q == "":
		return

	var data = await _api("action=search&q=" + q.uri_encode())
	result_list.clear()
	results = []

	if data == null:
		info_label.text = "Cannot reach the database. Is XAMPP running?"
		return
	if data.is_empty():
		info_label.text = "Nothing found for \"%s\"." % q
		return

	results = data
	for r in data:
		result_list.add_item("%s  (%s)" % [r["title"], r["genre"]])

	result_list.select(0)
	_on_result_selected(0)


func _on_result_selected(index: int) -> void:
	var r = results[index]
	_show_route(r["shelf_name"])
	info_label.text = "%s by %s\nGenre: %s" % [r["title"], r["author"], r["genre"]]


# ---------------------------------------------------------------- arrows
func _make_cone(top_r: float, bottom_r: float, h: float, color: Color) -> MeshInstance3D:
	var mesh := CylinderMesh.new()
	mesh.top_radius = top_r
	mesh.bottom_radius = bottom_r
	mesh.height = h
	var mat := StandardMaterial3D.new()
	mat.albedo_color = color
	mat.shading_mode = BaseMaterial3D.SHADING_MODE_UNSHADED
	mesh.material = mat
	var mi := MeshInstance3D.new()
	mi.mesh = mesh
	return mi


func _show_route(shelf_name: String) -> void:
	var shelf: Node3D = shelves.get(shelf_name)
	if shelf == null or entrance == null:
		info_label.text = "Shelf or Entrance not found in the model."
		return

	for c in path_root.get_children():
		c.queue_free()

	var start := entrance.global_position
	start.y = 0.0
	var shelf_pos := shelf.global_position
	shelf_pos.y = 0.0
	# stop a little in front of the shelf, on the entrance side
	var end := shelf_pos + (start - shelf_pos).normalized() * 1.8

	# row of arrows on the floor pointing from the entrance to the shelf
	var dir := (end - start).normalized()
	var length := start.distance_to(end)
	var step := 1.2
	var i := 1
	while i * step < length:
		var arrow := _make_cone(0.0, 0.3, 0.7, Color(1, 0.15, 0.15))
		path_root.add_child(arrow)
		arrow.global_position = start + dir * step * i + Vector3(0, 0.3, 0)
		arrow.look_at(arrow.global_position + dir, Vector3.UP)
		arrow.rotate_object_local(Vector3.RIGHT, -PI / 2)
		i += 1

	# big bouncing arrow above the shelf
	bounce_arrow = _make_cone(0.5, 0.0, 1.0, Color(1, 0.15, 0.15))
	path_root.add_child(bounce_arrow)
	bounce_base_y = shelf.global_position.y + shelf.get_aabb().end.y + 2.0
	bounce_arrow.global_position = Vector3(shelf.global_position.x, bounce_base_y, shelf.global_position.z)
